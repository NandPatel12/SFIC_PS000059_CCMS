<?php
namespace App\Controllers;

use App\Database;
use App\Services\AuthService;
use PDO;

class ApiController {
    public static function handle(): void {
        header('Content-Type: application/json; charset=utf-8');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        try {
            if ($path === '/api/login' && $method === 'POST') {
                self::login();
            } elseif ($path === '/api/logout' && $method === 'POST') {
                self::logout();
            } elseif ($path === '/api/me' && $method === 'GET') {
                self::me();
            } elseif ($path === '/api/dashboard/stats' && $method === 'GET') {
                self::dashboardStats();
            } elseif ($path === '/api/wards' && $method === 'GET') {
                self::getWards();
            } elseif ($path === '/api/feeders' && $method === 'GET') {
                self::getFeeders();
            } elseif ($path === '/api/luminaires' && $method === 'GET') {
                self::getLuminaires();
            } elseif ($path === '/api/alerts' && $method === 'GET') {
                self::getAlerts();
            } elseif ($path === '/api/alerts/acknowledge' && $method === 'POST') {
                self::acknowledgeAlert();
            } elseif ($path === '/api/work-orders' && $method === 'GET') {
                self::getWorkOrders();
            } elseif ($path === '/api/telemetry/simulate' && $method === 'POST') {
                self::simulateReading();
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'API Endpoint Not Found']);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private static function login(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$username || !$password) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Username and password required']);
            return;
        }

        $user = AuthService::authenticate($username, $password);
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => $user,
                'csrf_token' => AuthService::generateCsrfToken()
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
        }
    }

    private static function logout(): void {
        AuthService::logout();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    }

    private static function me(): void {
        $user = AuthService::getCurrentUser();
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user, 'csrf_token' => AuthService::generateCsrfToken()]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        }
    }

    private static function dashboardStats(): void {
        $db = Database::getConnection();

        $totalLuminaires = $db->query("SELECT COUNT(*) FROM luminaires")->fetchColumn() ?: 0;
        $activeLuminaires = $db->query("SELECT COUNT(*) FROM luminaires WHERE status = 'active'")->fetchColumn() ?: 0;
        $totalFeeders = $db->query("SELECT COUNT(*) FROM feeders")->fetchColumn() ?: 0;
        $activeAlerts = $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'")->fetchColumn() ?: 0;
        $openWorkOrders = $db->query("SELECT COUNT(*) FROM work_orders WHERE status != 'closed'")->fetchColumn() ?: 0;
        
        // Sum latest power
        $currentKw = $db->query("SELECT SUM(power_kw) FROM device_latest_state WHERE asset_type = 'feeder'")->fetchColumn() ?: 0.0;

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_luminaires' => (int)$totalLuminaires,
                'active_luminaires' => (int)$activeLuminaires,
                'total_feeders' => (int)$totalFeeders,
                'active_alerts' => (int)$activeAlerts,
                'open_work_orders' => (int)$openWorkOrders,
                'current_load_kw' => round((float)$currentKw, 2),
                'estimated_today_kwh' => round((float)$currentKw * 7.5, 1) // ~7.5 operating hours baseline
            ]
        ]);
    }

    private static function getWards(): void {
        $db = Database::getConnection();
        $wards = $db->query("
            SELECT w.*, 
            (SELECT COUNT(*) FROM feeders WHERE ward_id = w.id) as feeder_count 
            FROM wards w ORDER BY w.id ASC
        ")->fetchAll();
        echo json_encode(['success' => true, 'wards' => $wards]);
    }

    private static function getFeeders(): void {
        $db = Database::getConnection();
        $feeders = $db->query("
            SELECT f.*, w.name as ward_name, dls.reported_state, dls.voltage_v, dls.power_kw, dls.is_online, dls.last_seen_at
            FROM feeders f
            LEFT JOIN wards w ON f.ward_id = w.id
            LEFT JOIN device_latest_state dls ON dls.asset_type = 'feeder' AND dls.asset_id = f.id
            ORDER BY f.id ASC
        ")->fetchAll();
        echo json_encode(['success' => true, 'feeders' => $feeders]);
    }

    private static function getLuminaires(): void {
        $db = Database::getConnection();
        $luminaires = $db->query("
            SELECT l.*, p.name as panel_name, f.feeder_code, w.name as ward_name,
                   dls.commanded_state, dls.reported_state, dls.brightness_pct, dls.voltage_v, dls.last_seen_at
            FROM luminaires l
            JOIN panels p ON l.panel_id = p.id
            JOIN feeders f ON p.feeder_id = f.id
            JOIN wards w ON f.ward_id = w.id
            LEFT JOIN device_latest_state dls ON dls.asset_type = 'luminaire' AND dls.asset_id = l.id
            ORDER BY l.id ASC
        ")->fetchAll();
        echo json_encode(['success' => true, 'luminaires' => $luminaires]);
    }

    private static function getAlerts(): void {
        $db = Database::getConnection();
        $alerts = $db->query("
            SELECT a.*, u.full_name as acknowledged_by_name
            FROM alerts a
            LEFT JOIN users u ON a.acknowledged_by = u.id
            ORDER BY a.created_at DESC
            LIMIT 50
        ")->fetchAll();
        echo json_encode(['success' => true, 'alerts' => $alerts]);
    }

    private static function acknowledgeAlert(): void {
        $user = AuthService::getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $alertId = (int)($input['alert_id'] ?? 0);

        if (!$alertId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Alert ID required']);
            return;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE alerts 
            SET status = 'acknowledged', acknowledged_by = :uid, acknowledged_at = datetime('now')
            WHERE id = :id AND status = 'active'
        ");
        $stmt->execute([':uid' => $user['id'], ':id' => $alertId]);

        AuthService::logAudit($user['id'], 'ALERT_ACKNOWLEDGE', 'alerts', (string)$alertId, []);
        echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
    }

    private static function getWorkOrders(): void {
        $db = Database::getConnection();
        $orders = $db->query("
            SELECT wo.*, u.full_name as assigned_to_name
            FROM work_orders wo
            LEFT JOIN users u ON wo.assigned_to = u.id
            ORDER BY wo.created_at DESC
        ")->fetchAll();
        echo json_encode(['success' => true, 'work_orders' => $orders]);
    }

    private static function simulateReading(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $feederId = (int)($input['feeder_id'] ?? 1);
        $vR = (float)($input['v_r'] ?? 230.5);
        $powerKw = (float)($input['power_kw'] ?? 5.4);

        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE device_latest_state 
            SET voltage_v = :v, power_kw = :kw, last_seen_at = datetime('now'), is_online = 1
            WHERE asset_type = 'feeder' AND asset_id = :fid
        ");
        $stmt->execute([':v' => $vR, ':kw' => $powerKw, ':fid' => $feederId]);

        echo json_encode(['success' => true, 'message' => 'Simulated telemetry ingested successfully']);
    }
}

