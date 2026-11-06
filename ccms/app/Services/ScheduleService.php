<?php
// Lighting Schedules, Astronomical Switching & Device Command Queue Service
namespace App\Services;

use App\Database;
use PDO;

class ScheduleService {
    public const GANDHIDHAM_LAT = 23.0753;
    public const GANDHIDHAM_LON = 70.1337;

    /**
     * Compute exact astronomical sunrise, sunset and twilight times for Gandhidham (Asia/Kolkata)
     */
    public static function getAstronomicalTimes(?int $timestamp = null): array {
        date_default_timezone_set('Asia/Kolkata');
        $ts = $timestamp ?? time();
        $sunInfo = date_sun_info($ts, self::GANDHIDHAM_LAT, self::GANDHIDHAM_LON);

        return [
            'date' => date('Y-m-d', $ts),
            'latitude' => self::GANDHIDHAM_LAT,
            'longitude' => self::GANDHIDHAM_LON,
            'timezone' => 'Asia/Kolkata',
            'sunrise' => date('H:i', $sunInfo['sunrise']),
            'sunset' => date('H:i', $sunInfo['sunset']),
            'civil_twilight_begin' => date('H:i', $sunInfo['civil_twilight_begin']),
            'civil_twilight_end' => date('H:i', $sunInfo['civil_twilight_end']),
            'recommended_switch_on' => date('H:i', $sunInfo['sunset']),
            'recommended_switch_off' => date('H:i', $sunInfo['sunrise']),
            'current_time_ist' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get all lighting schedules with ward and approver info
     */
    public static function getSchedules(?int $wardId = null): array {
        $db = Database::getConnection();
        $sql = "
            SELECT s.*, w.name as ward_name, f.feeder_code, u.full_name as approver_name
            FROM lighting_schedules s
            LEFT JOIN wards w ON s.ward_id = w.id
            LEFT JOIN feeders f ON s.feeder_id = f.id
            LEFT JOIN users u ON s.approved_by = u.id
        ";
        $params = [];
        if ($wardId) {
            $sql .= " WHERE s.ward_id = :ward_id OR s.ward_id IS NULL";
            $params[':ward_id'] = $wardId;
        }
        $sql .= " ORDER BY s.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            if (!empty($r['dimming_profile_json']) && is_string($r['dimming_profile_json'])) {
                $r['dimming_profile'] = json_decode($r['dimming_profile_json'], true);
            } else {
                $r['dimming_profile'] = [];
            }
        }
        return $rows;
    }

    /**
     * Create a new lighting schedule
     */
    public static function createSchedule(array $data, int $userId): array {
        $db = Database::getConnection();
        $name = trim($data['name'] ?? 'Custom Schedule');
        $wardId = !empty($data['ward_id']) ? (int)$data['ward_id'] : null;
        $feederId = !empty($data['feeder_id']) ? (int)$data['feeder_id'] : null;
        $isAstro = !empty($data['is_astronomical']) ? 1 : 0;
        $sunsetOffset = (int)($data['sunset_offset_mins'] ?? 0);
        $sunriseOffset = (int)($data['sunrise_offset_mins'] ?? 0);
        $fixedOn = !empty($data['fixed_on_time']) ? $data['fixed_on_time'] : null;
        $fixedOff = !empty($data['fixed_off_time']) ? $data['fixed_off_time'] : null;
        $dimming = isset($data['dimming_profile']) ? json_encode($data['dimming_profile']) : '[]';

        $stmt = $db->prepare("
            INSERT INTO lighting_schedules 
            (name, ward_id, feeder_id, is_astronomical, sunset_offset_mins, sunrise_offset_mins, fixed_on_time, fixed_off_time, dimming_profile_json, status, approved_by, approved_at, created_at, updated_at)
            VALUES (:name, :ward_id, :feeder_id, :is_astro, :sunset_off, :sunrise_off, :fixed_on, :fixed_off, :dimming, 'active', :user_id, NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            ':name' => $name,
            ':ward_id' => $wardId,
            ':feeder_id' => $feederId,
            ':is_astro' => $isAstro,
            ':sunset_off' => $sunsetOffset,
            ':sunrise_off' => $sunriseOffset,
            ':fixed_on' => $fixedOn,
            ':fixed_off' => $fixedOff,
            ':dimming' => $dimming,
            ':user_id' => $userId
        ]);
        $newId = (int)$db->lastInsertId();

        AuthService::logAudit($userId, 'CREATE_SCHEDULE', 'lighting_schedules', (string)$newId, ['name' => $name]);

        return ['id' => $newId, 'name' => $name, 'status' => 'active'];
    }

    /**
     * Dispatch a command to a feeder or luminaire
     */
    public static function queueCommand(
        string $targetType,
        int $targetId,
        string $commandType,
        array $params,
        string $reason,
        int $userId,
        int $durationMins = 60
    ): array {
        $db = Database::getConnection();
        $code = 'CMD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $expiry = date('Y-m-d H:i:s', time() + ($durationMins * 60));

        $stmt = $db->prepare("
            INSERT INTO device_commands 
            (command_code, target_type, target_id, command_type, parameters_json, reason, status, issued_by, expires_at, created_at, updated_at)
            VALUES (:code, :target_type, :target_id, :cmd_type, :params, :reason, 'dispatched', :user_id, :expires, NOW(), NOW())
        ");
        $stmt->execute([
            ':code' => $code,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':cmd_type' => $commandType,
            ':params' => json_encode($params),
            ':reason' => $reason,
            ':user_id' => $userId,
            ':expires' => $expiry
        ]);
        $newId = (int)$db->lastInsertId();

        // Update commanded state in device_latest_state cache
        $commandedState = in_array($commandType, ['SWITCH_ON', 'MANUAL_OVERRIDE']) ? 'ON' : ($commandType === 'SWITCH_OFF' ? 'OFF' : null);
        $brightness = $params['brightness_pct'] ?? null;

        if ($commandedState !== null || $brightness !== null) {
            $updateSql = "UPDATE device_latest_state SET updated_at = NOW()";
            $updateParams = [':target_type' => $targetType, ':target_id' => $targetId];
            if ($commandedState !== null) {
                $updateSql .= ", commanded_state = :cstate";
                $updateParams[':cstate'] = $commandedState;
            }
            if ($brightness !== null) {
                $updateSql .= ", brightness_pct = :bright";
                $updateParams[':bright'] = (int)$brightness;
            }
            $updateSql .= " WHERE asset_type = :target_type AND asset_id = :target_id";
            $db->prepare($updateSql)->execute($updateParams);
        }

        AuthService::logAudit($userId, 'DISPATCH_COMMAND', 'device_commands', (string)$newId, [
            'code' => $code,
            'type' => $commandType,
            'target_type' => $targetType,
            'target_id' => $targetId
        ]);

        return [
            'id' => $newId,
            'command_code' => $code,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'status' => 'dispatched',
            'expires_at' => $expiry
        ];
    }

    /**
     * Acknowledge command completion (IoT gateway simulator ACK)
     */
    public static function acknowledgeCommand(int $commandId, array $response = []): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE device_commands 
            SET status = 'acknowledged', acknowledged_at = NOW(), response_payload_json = :resp, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':resp' => json_encode($response ?: ['acknowledged' => true, 'timestamp' => date('Y-m-d H:i:s')]),
            ':id' => $commandId
        ]);
    }

    /**
     * Fetch recent device commands
     */
    public static function getCommands(int $limit = 50): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, u.full_name as issued_by_name
            FROM device_commands c
            LEFT JOIN users u ON c.issued_by = u.id
            ORDER BY c.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['parameters'] = !empty($r['parameters_json']) ? json_decode($r['parameters_json'], true) : [];
        }
        return $rows;
    }
}
