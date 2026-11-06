<?php
namespace App\Services;

use App\Database;
use PDO;

class TelemetryService {
    /**
     * Ingests, validates, deduplicates, and evaluates Feeder Telemetry payload.
     */
    public static function ingestFeederTelemetry(array $payload): array {
        $db = Database::getConnection();

        $deviceId = trim($payload['device_id'] ?? '');
        $messageId = trim($payload['message_id'] ?? '');
        $recordedAt = trim($payload['recorded_at'] ?? date('Y-m-d H:i:s'));

        if (!$deviceId || !$messageId) {
            throw new \InvalidArgumentException("device_id and message_id are mandatory");
        }

        // 1. Authenticate / Validate Device
        $stmt = $db->prepare("SELECT id, feeder_code FROM feeders WHERE feeder_code = :code LIMIT 1");
        $stmt->execute([':code' => $deviceId]);
        $feeder = $stmt->fetch();
        if (!$feeder) {
            throw new \RuntimeException("Unauthorized or unmapped feeder device: {$deviceId}");
        }
        $feederId = (int)$feeder['id'];

        // 2. Message Deduplication (Reject duplicate message IDs)
        $dupCheck = $db->prepare("SELECT id FROM telemetry_feeder_readings WHERE message_id = :mid LIMIT 1");
        $dupCheck->execute([':mid' => $messageId]);
        if ($dupCheck->fetch()) {
            return [
                'status' => 'duplicate_ignored',
                'device_id' => $deviceId,
                'message_id' => $messageId
            ];
        }

        // 3. Extract & Validate Physical Readings
        $vR = (float)($payload['v_r'] ?? 230.0);
        $vY = (float)($payload['v_y'] ?? 230.0);
        $vB = (float)($payload['v_b'] ?? 230.0);
        $iR = (float)($payload['i_r'] ?? 0.0);
        $iY = (float)($payload['i_y'] ?? 0.0);
        $iB = (float)($payload['i_b'] ?? 0.0);
        $totalKw = (float)($payload['total_power_kw'] ?? (($vR * $iR + $vY * $iY + $vB * $iB) / 1000.0));
        $powerFactor = (float)($payload['power_factor'] ?? 0.95);
        $kwh = (float)($payload['cumulative_kwh'] ?? 0.0);
        $phaseR = !empty($payload['phase_status_r']) ? 1 : 0;
        $phaseY = !empty($payload['phase_status_y']) ? 1 : 0;
        $phaseB = !empty($payload['phase_status_b']) ? 1 : 0;
        $contactorStatus = !empty($payload['contactor_status']) ? 1 : 0;

        // 4. Persist Historical Reading
        $ins = $db->prepare("
            INSERT INTO telemetry_feeder_readings (
                feeder_id, message_id, v_r, v_y, v_b, i_r, i_y, i_b, 
                total_power_kw, power_factor, cumulative_kwh, 
                phase_status_r, phase_status_y, phase_status_b, contactor_status, 
                recorded_at, created_at
            ) VALUES (
                :fid, :mid, :vr, :vy, :vb, :ir, :iy, :ib, 
                :kw, :pf, :kwh, 
                :pr, :py, :pb, :cs, 
                :rec_at, datetime('now')
            )
        ");
        $ins->execute([
            ':fid' => $feederId,
            ':mid' => $messageId,
            ':vr' => $vR, ':vy' => $vY, ':vb' => $vB,
            ':ir' => $iR, ':iy' => $iY, ':ib' => $iB,
            ':kw' => $totalKw, ':pf' => $powerFactor, ':kwh' => $kwh,
            ':pr' => $phaseR, ':py' => $phaseY, ':pb' => $phaseB, ':cs' => $contactorStatus,
            ':rec_at' => $recordedAt
        ]);

        // 5. Update Latest Device State Cache
        $avgV = round(($vR + $vY + $vB) / 3, 2);
        $totalI = round($iR + $iY + $iB, 2);
        $reportedState = $contactorStatus ? 'ON' : 'OFF';

        $updState = $db->prepare("
            INSERT INTO device_latest_state (asset_type, asset_id, reported_state, voltage_v, current_a, power_kw, power_factor, is_online, last_seen_at)
            VALUES ('feeder', :fid, :rep_st, :v, :i, :kw, :pf, 1, datetime('now'))
            ON CONFLICT(asset_type, asset_id) DO UPDATE SET
                reported_state = :rep_st,
                voltage_v = :v,
                current_a = :i,
                power_kw = :kw,
                power_factor = :pf,
                is_online = 1,
                last_seen_at = datetime('now')
        ");
        $updState->execute([
            ':fid' => $feederId,
            ':rep_st' => $reportedState,
            ':v' => $avgV,
            ':i' => $totalI,
            ':kw' => $totalKw,
            ':pf' => $powerFactor
        ]);

        // 6. Real-time Fault Evaluation
        $alerts = FaultService::evaluateFeederFaults($feederId, [
            'v_r' => $vR, 'v_y' => $vY, 'v_b' => $vB,
            'phase_status_r' => $phaseR, 'phase_status_y' => $phaseY, 'phase_status_b' => $phaseB,
            'total_power_kw' => $totalKw
        ]);

        return [
            'status' => 'persisted',
            'feeder_id' => $feederId,
            'device_id' => $deviceId,
            'reported_state' => $reportedState,
            'active_faults_triggered' => $alerts
        ];
    }

    /**
     * Ingests, validates, deduplicates, and evaluates Luminaire Telemetry payload.
     */
    public static function ingestLuminaireTelemetry(array $payload): array {
        $db = Database::getConnection();

        $deviceId = trim($payload['device_id'] ?? '');
        $messageId = trim($payload['message_id'] ?? '');
        $recordedAt = trim($payload['recorded_at'] ?? date('Y-m-d H:i:s'));

        if (!$deviceId || !$messageId) {
            throw new \InvalidArgumentException("device_id and message_id are mandatory");
        }

        // 1. Authenticate / Validate Device
        $stmt = $db->prepare("SELECT id, pole_number FROM luminaires WHERE pole_number = :code LIMIT 1");
        $stmt->execute([':code' => $deviceId]);
        $lum = $stmt->fetch();
        if (!$lum) {
            throw new \RuntimeException("Unauthorized or unmapped luminaire: {$deviceId}");
        }
        $lumId = (int)$lum['id'];

        // 2. Deduplication check
        $dupCheck = $db->prepare("SELECT id FROM telemetry_luminaire_readings WHERE message_id = :mid LIMIT 1");
        $dupCheck->execute([':mid' => $messageId]);
        if ($dupCheck->fetch()) {
            return [
                'status' => 'duplicate_ignored',
                'device_id' => $deviceId,
                'message_id' => $messageId
            ];
        }

        // 3. Values
        $lampStatus = !empty($payload['lamp_status']) ? 1 : 0;
        $brightness = (int)($payload['brightness_pct'] ?? ($lampStatus ? 100 : 0));
        $voltage = (float)($payload['input_voltage_v'] ?? 230.0);
        $powerW = (float)($payload['power_w'] ?? 0.0);

        // 4. Persist
        $ins = $db->prepare("
            INSERT INTO telemetry_luminaire_readings (luminaire_id, message_id, lamp_status, brightness_pct, input_voltage_v, power_w, recorded_at, created_at)
            VALUES (:lid, :mid, :lst, :br, :v, :pw, :rec_at, datetime('now'))
        ");
        $ins->execute([
            ':lid' => $lumId,
            ':mid' => $messageId,
            ':lst' => $lampStatus,
            ':br' => $brightness,
            ':v' => $voltage,
            ':pw' => $powerW,
            ':rec_at' => $recordedAt
        ]);

        // Get commanded state
        $cmdStmt = $db->prepare("SELECT commanded_state FROM device_latest_state WHERE asset_type = 'luminaire' AND asset_id = :aid");
        $cmdStmt->execute([':aid' => $lumId]);
        $commandedState = $cmdStmt->fetchColumn() ?: 'ON';

        // 5. Update latest state
        $reportedState = $lampStatus ? 'ON' : 'OFF';
        $updState = $db->prepare("
            INSERT INTO device_latest_state (asset_type, asset_id, reported_state, brightness_pct, voltage_v, power_kw, is_online, last_seen_at)
            VALUES ('luminaire', :lid, :rep_st, :br, :v, :kw, 1, datetime('now'))
            ON CONFLICT(asset_type, asset_id) DO UPDATE SET
                reported_state = :rep_st,
                brightness_pct = :br,
                voltage_v = :v,
                power_kw = :kw,
                is_online = 1,
                last_seen_at = datetime('now')
        ");
        $updState->execute([
            ':lid' => $lumId,
            ':rep_st' => $reportedState,
            ':br' => $brightness,
            ':v' => $voltage,
            ':kw' => round($powerW / 1000.0, 4)
        ]);

        // 6. Fault evaluation
        $alerts = FaultService::evaluateLuminaireFaults($lumId, [
            'lamp_status' => $lampStatus,
            'power_w' => $powerW,
            'brightness_pct' => $brightness
        ], $commandedState);

        return [
            'status' => 'persisted',
            'luminaire_id' => $lumId,
            'device_id' => $deviceId,
            'reported_state' => $reportedState,
            'active_faults_triggered' => $alerts
        ];
    }
}

