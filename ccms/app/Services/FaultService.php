<?php
namespace App\Services;

use App\Database;
use PDO;

class FaultService {
    /**
     * Evaluates feeder telemetry against configurable fault rules.
     */
    public static function evaluateFeederFaults(int $feederId, array $reading): array {
        $db = Database::getConnection();
        $generatedAlerts = [];

        $vR = (float)($reading['v_r'] ?? 0);
        $vY = (float)($reading['v_y'] ?? 0);
        $vB = (float)($reading['v_b'] ?? 0);
        $totalKw = (float)($reading['total_power_kw'] ?? 0);
        $phaseR = !empty($reading['phase_status_r']);
        $phaseY = !empty($reading['phase_status_y']);
        $phaseB = !empty($reading['phase_status_b']);

        // Feeder details
        $stmt = $db->prepare("SELECT feeder_code, rated_capacity_kw FROM feeders WHERE id = :id");
        $stmt->execute([':id' => $feederId]);
        $feeder = $stmt->fetch();
        $feederCode = $feeder['feeder_code'] ?? "FDR-{$feederId}";
        $ratedCapacity = (float)($feeder['rated_capacity_kw'] ?? 15.0);

        // 1. Phase Failure Rule (<160V or phase lost flag)
        if (!$phaseR || !$phaseY || !$phaseB || $vR < 160 || $vY < 160 || $vB < 160) {
            $lostPhases = [];
            if (!$phaseR || $vR < 160) $lostPhases[] = "R ({$vR}V)";
            if (!$phaseY || $vY < 160) $lostPhases[] = "Y ({$vY}V)";
            if (!$phaseB || $vB < 160) $lostPhases[] = "B ({$vB}V)";
            $evidence = [
                'rule' => 'FLT_PHASE_FAIL',
                'lost_phases' => $lostPhases,
                'voltages' => ['R' => $vR, 'Y' => $vY, 'B' => $vB]
            ];
            $alert = self::triggerAlert(
                'feeder',
                $feederId,
                'FLT_PHASE_FAIL',
                'critical',
                "Phase trip / under-voltage on Feeder {$feederCode}: " . implode(', ', $lostPhases),
                $evidence
            );
            if ($alert) $generatedAlerts[] = $alert;
        }

        // 2. Voltage Surge Rule (>270V)
        if ($vR > 270 || $vY > 270 || $vB > 270) {
            $evidence = ['v_r' => $vR, 'v_y' => $vY, 'v_b' => $vB];
            $alert = self::triggerAlert(
                'feeder',
                $feederId,
                'FLT_VOLT_SURGE',
                'critical',
                "Dangerous voltage surge detected on Feeder {$feederCode} (>270V)",
                $evidence
            );
            if ($alert) $generatedAlerts[] = $alert;
        }

        // 3. Energy Anomaly Rule (Measured power exceeds rated capacity by > 20%)
        if ($ratedCapacity > 0 && $totalKw > ($ratedCapacity * 1.2)) {
            $evidence = ['measured_kw' => $totalKw, 'rated_capacity_kw' => $ratedCapacity, 'overload_pct' => round((($totalKw / $ratedCapacity) - 1) * 100, 1)];
            $alert = self::triggerAlert(
                'feeder',
                $feederId,
                'FLT_ENERGY_ANOMALY',
                'warning',
                "Energy anomaly on Feeder {$feederCode}: Measured {$totalKw} kW exceeds rated capacity {$ratedCapacity} kW",
                $evidence
            );
            if ($alert) $generatedAlerts[] = $alert;
        }

        return $generatedAlerts;
    }

    /**
     * Evaluates luminaire telemetry against operational rules.
     */
    public static function evaluateLuminaireFaults(int $luminaireId, array $reading, string $commandedState): array {
        $db = Database::getConnection();
        $generatedAlerts = [];

        $lampStatus = !empty($reading['lamp_status']);
        $powerW = (float)($reading['power_w'] ?? 0);
        $brightness = (int)($reading['brightness_pct'] ?? 0);

        $stmt = $db->prepare("SELECT pole_number, wattage FROM luminaires WHERE id = :id");
        $stmt->execute([':id' => $luminaireId]);
        $lum = $stmt->fetch();
        $pole = $lum['pole_number'] ?? "POLE-{$luminaireId}";

        // 1. Commanded ON but Reported OFF / 0W (Lamp Failure)
        if ($commandedState === 'ON' && (!$lampStatus || $powerW < 10.0)) {
            $evidence = ['commanded' => 'ON', 'reported_lamp_status' => $lampStatus, 'measured_power_w' => $powerW];
            $alert = self::triggerAlert(
                'luminaire',
                $luminaireId,
                'FLT_LAMP_FAIL',
                'critical',
                "Lamp failure on Pole {$pole}: Commanded ON but reported OFF/no current ({$powerW}W)",
                $evidence
            );
            if ($alert) $generatedAlerts[] = $alert;
        }

        // 2. Day-Burning Rule: Light reported ON during daytime (e.g. commanded OFF outside schedule)
        if ($commandedState === 'OFF' && $lampStatus && $powerW > 15.0) {
            $evidence = ['commanded' => 'OFF', 'reported_lamp_status' => true, 'power_w' => $powerW];
            $alert = self::triggerAlert(
                'luminaire',
                $luminaireId,
                'FLT_DAY_BURNING',
                'warning',
                "Day-burning detected on Pole {$pole}: Fixture drawing {$powerW}W while commanded OFF",
                $evidence
            );
            if ($alert) $generatedAlerts[] = $alert;
        }

        return $generatedAlerts;
    }

    /**
     * Deduplicates and triggers an alert. If an active alert already exists for the asset + fault code, it does not duplicate.
     */
    public static function triggerAlert(string $assetType, int $assetId, string $faultCode, string $severity, string $summary, array $evidence): ?array {
        $db = Database::getConnection();

        // Check for existing active or acknowledged alert for this asset and fault code
        $stmt = $db->prepare("
            SELECT id, status, fault_code FROM alerts 
            WHERE asset_type = :atype AND asset_id = :aid AND fault_code = :fcode AND status IN ('active', 'acknowledged')
            LIMIT 1
        ");
        $stmt->execute([
            ':atype' => $assetType,
            ':aid' => $assetId,
            ':fcode' => $faultCode
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Duplicate detected; update timestamp and evidence without creating new alert
            $uStmt = $db->prepare("UPDATE alerts SET evidence_json = :ev, updated_at = datetime('now') WHERE id = :id");
            $uStmt->execute([':ev' => json_encode($evidence), ':id' => $existing['id']]);
            return null;
        }

        // Insert new active alert
        $ins = $db->prepare("
            INSERT INTO alerts (asset_type, asset_id, fault_code, severity, summary, evidence_json, status, created_at, updated_at)
            VALUES (:atype, :aid, :fcode, :sev, :sum, :ev, 'active', datetime('now'), datetime('now'))
        ");
        $ins->execute([
            ':atype' => $assetType,
            ':aid' => $assetId,
            ':fcode' => $faultCode,
            ':sev' => $severity,
            ':sum' => $summary,
            ':ev' => json_encode($evidence)
        ]);
        $newId = (int)$db->lastInsertId();

        // Send Mock SMS Notification (Test Mode)
        self::sendSmsNotification($newId, $severity, $summary);

        return [
            'id' => $newId,
            'asset_type' => $assetType,
            'asset_id' => $assetId,
            'fault_code' => $faultCode,
            'severity' => $severity,
            'summary' => $summary
        ];
    }

    public static function sendSmsNotification(int $alertId, string $severity, string $summary): void {
        // Sandboxed SMS notification logger (Mock Gateway)
        $logPath = __DIR__ . '/../../storage/logs/sms_sandbox.log';
        @mkdir(dirname($logPath), 0777, true);
        $logEntry = sprintf(
            "[%s] [SMS TEST GATEWAY] Recipient: Line Supervisors & EE | Alert #%d [%s]: %s\n",
            date('Y-m-d H:i:s'),
            $alertId,
            strtoupper($severity),
            $summary
        );
        @file_put_contents($logPath, $logEntry, FILE_APPEND);
    }
}

