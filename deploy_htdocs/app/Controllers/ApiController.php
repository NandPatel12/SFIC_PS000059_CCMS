<?php
namespace App\Controllers;

use App\Database;
use App\Services\AuthService;
use App\Services\ScheduleService;
use App\Services\WorkOrderService;
use App\Services\ReportService;
use PDO;

class ApiController {
    public static function handle(): void {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // CSV Export endpoints output text/csv
        if ($path === '/api/reports/export/energy') {
            self::exportEnergyCsv();
            return;
        } elseif ($path === '/api/reports/export/work-orders') {
            self::exportWorkOrdersCsv();
            return;
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            // Auth
            if ($path === '/api/login' && $method === 'POST') {
                self::login();
            } elseif ($path === '/api/logout' && $method === 'POST') {
                self::logout();
            } elseif ($path === '/api/me' && $method === 'GET') {
                self::me();
            }
            // Dashboard & Registry (Phases 1 & 2)
            elseif ($path === '/api/dashboard/stats' && $method === 'GET') {
                self::dashboardStats();
            } elseif ($path === '/api/wards' && $method === 'GET') {
                self::getWards();
            } elseif ($path === '/api/feeders' && $method === 'GET') {
                self::getFeeders();
            } elseif ($path === '/api/luminaires' && $method === 'GET') {
                self::getLuminaires();
            }
            // Faults & Alerts (Phase 3)
            elseif ($path === '/api/alerts' && $method === 'GET') {
                self::getAlerts();
            } elseif ($path === '/api/alerts/acknowledge' && $method === 'POST') {
                self::acknowledgeAlert();
            } elseif ($path === '/api/alerts/resolve' && $method === 'POST') {
                self::resolveAlert();
            }
            // Lighting Schedules & Device Commands (Phase 4)
            elseif ($path === '/api/schedules' && $method === 'GET') {
                self::getSchedules();
            } elseif ($path === '/api/schedules/astronomical' && $method === 'GET') {
                self::getAstronomical();
            } elseif ($path === '/api/schedules/create' && $method === 'POST') {
                self::createSchedule();
            } elseif ($path === '/api/commands' && $method === 'GET') {
                self::getCommands();
            } elseif ($path === '/api/commands/dispatch' && $method === 'POST') {
                self::dispatchCommand();
            } elseif ($path === '/api/commands/acknowledge' && $method === 'POST') {
                self::acknowledgeCommand();
            } elseif ($path === '/api/commands/override' && $method === 'POST') {
                self::manualOverride();
            }
            // Work Orders & Energy Reporting (Phase 5)
            elseif ($path === '/api/work-orders' && $method === 'GET') {
                self::getWorkOrders();
            } elseif ($path === '/api/work-orders/create-from-alert' && $method === 'POST') {
                self::createWorkOrderFromAlert();
            } elseif ($path === '/api/work-orders/update-status' && $method === 'POST') {
                self::updateWorkOrderStatus();
            } elseif ($path === '/api/work-orders/events' && $method === 'GET') {
                self::getWorkOrderEvents();
            } elseif ($path === '/api/reports/energy' && $method === 'GET') {
                self::getEnergyReport();
            } elseif ($path === '/api/reports/kpi' && $method === 'GET') {
                self::getMaintenanceKpis();
            }
            // Simulator
            elseif ($path === '/api/telemetry/simulate' && $method === 'POST') {
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
            echo json_encode([
                'success' => true,
                'user' => $user,
                'csrf_token' => AuthService::generateCsrfToken()
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthenticated session']);
        }
    }

    private static function dashboardStats(): void {
        $db = Database::getConnection();

        $totalLuminaires = $db->query("SELECT COUNT(*) FROM luminaires")->fetchColumn() ?: 0;
        $activeLuminaires = $db->query("SELECT COUNT(*) FROM luminaires WHERE status = 'active'")->fetchColumn() ?: 0;
        $totalFeeders = $db->query("SELECT COUNT(*) FROM feeders")->fetchColumn() ?: 0;
        $activeAlerts = $db->query("SELECT COUNT(*) FROM alerts WHERE status = 'active'")->fetchColumn() ?: 0;
        $openWorkOrders = $db->query("SELECT COUNT(*) FROM work_orders WHERE status != 'closed'")->fetchColumn() ?: 0;

        $kwStmt = $db->query("SELECT SUM(power_kw) FROM device_latest_state WHERE asset_type = 'feeder'");
        $currentKw = $kwStmt->fetchColumn() ?: 0.0;

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_luminaires' => (int)$totalLuminaires,
                'active_luminaires' => (int)$activeLuminaires,
                'total_feeders' => (int)$totalFeeders,
                'active_alerts' => (int)$activeAlerts,
                'open_work_orders' => (int)$openWorkOrders,
                'current_load_kw' => round((float)$currentKw, 2),
                'estimated_today_kwh' => round((float)$currentKw * 8.2, 1)
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
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $alertId = (int)($input['alert_id'] ?? 0);
        $userId = $user['id'] ?? 1;

        if (!$alertId) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Alert ID required']);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE alerts 
            SET status = 'acknowledged', acknowledged_by = :uid, acknowledged_at = :now, updated_at = :now
            WHERE id = :id AND status = 'active'
        ");
        $stmt->execute([':uid' => $userId, ':id' => $alertId, ':now' => $now]);

        AuthService::logAudit($userId, 'ALERT_ACKNOWLEDGE', 'alerts', (string)$alertId, []);
        echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
    }

    private static function resolveAlert(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $alertId = (int)($input['alert_id'] ?? 0);
        $notes = trim($input['notes'] ?? 'Resolved by operator');
        $userId = $user['id'] ?? 1;

        $now = date('Y-m-d H:i:s');
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE alerts 
            SET status = 'resolved', resolved_by = :uid, resolved_at = :now, resolution_notes = :notes, updated_at = :now
            WHERE id = :id
        ");
        $stmt->execute([':uid' => $userId, ':notes' => $notes, ':id' => $alertId, ':now' => $now]);

        echo json_encode(['success' => true, 'message' => 'Alert marked resolved']);
    }

    // --- Phase 4: Schedules & Commands ---

    private static function getSchedules(): void {
        $schedules = ScheduleService::getSchedules();
        echo json_encode(['success' => true, 'schedules' => $schedules]);
    }

    private static function getAstronomical(): void {
        $times = ScheduleService::getAstronomicalTimes();
        echo json_encode(['success' => true, 'astronomical' => $times]);
    }

    private static function createSchedule(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = ScheduleService::createSchedule($input, $user['id'] ?? 1);
        echo json_encode(['success' => true, 'schedule' => $result]);
    }

    private static function getCommands(): void {
        $commands = ScheduleService::getCommands();
        echo json_encode(['success' => true, 'commands' => $commands]);
    }

    private static function dispatchCommand(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $targetType = $input['target_type'] ?? 'feeder';
        $targetId = (int)($input['target_id'] ?? 1);
        $commandType = $input['command_type'] ?? 'SWITCH_ON';
        $params = $input['parameters'] ?? [];
        $reason = $input['reason'] ?? 'Manual dispatch via CCMS portal';

        $res = ScheduleService::queueCommand($targetType, $targetId, $commandType, $params, $reason, $user['id'] ?? 1);
        echo json_encode(['success' => true, 'command' => $res]);
    }

    private static function acknowledgeCommand(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $cmdId = (int)($input['command_id'] ?? 0);
        ScheduleService::acknowledgeCommand($cmdId, ['status' => 'SUCCESS', 'gateway' => '4G_MODBUS_GW01']);
        echo json_encode(['success' => true, 'message' => 'Command acknowledged by device gateway']);
    }

    private static function manualOverride(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $targetType = $input['target_type'] ?? 'feeder';
        $targetId = (int)($input['target_id'] ?? 1);
        $state = strtoupper($input['state'] ?? 'ON');
        $brightness = (int)($input['brightness_pct'] ?? 100);
        $duration = (int)($input['duration_mins'] ?? 60);
        $reason = trim($input['reason'] ?? 'Maintenance override');

        $cmd = ScheduleService::queueCommand(
            $targetType,
            $targetId,
            'MANUAL_OVERRIDE',
            ['state' => $state, 'brightness_pct' => $brightness, 'duration_mins' => $duration],
            $reason,
            $user['id'] ?? 1,
            $duration
        );
        echo json_encode(['success' => true, 'override' => $cmd]);
    }

    // --- Phase 5: Work Orders & Energy Reports ---

    private static function getWorkOrders(): void {
        $status = $_GET['status'] ?? null;
        $orders = WorkOrderService::getWorkOrders($status);
        echo json_encode(['success' => true, 'work_orders' => $orders]);
    }

    private static function createWorkOrderFromAlert(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $alertId = (int)($input['alert_id'] ?? 0);
        $assignedTo = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : 4;
        $priority = $input['priority'] ?? 'medium';
        $title = $input['title'] ?? null;

        $order = WorkOrderService::createFromAlert($alertId, $assignedTo, $priority, $title, $user['id'] ?? 1);
        echo json_encode(['success' => true, 'work_order' => $order]);
    }

    private static function updateWorkOrderStatus(): void {
        $user = AuthService::getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $woId = (int)($input['work_order_id'] ?? 0);
        $newStatus = $input['status'] ?? 'in_progress';
        $notes = $input['notes'] ?? null;
        $evidence = $input['repair_evidence_notes'] ?? null;

        $ok = WorkOrderService::updateStatus($woId, $newStatus, $notes, $evidence, $user['id'] ?? 1);
        echo json_encode(['success' => $ok]);
    }

    private static function getWorkOrderEvents(): void {
        $woId = (int)($_GET['work_order_id'] ?? 0);
        $events = WorkOrderService::getEvents($woId);
        echo json_encode(['success' => true, 'events' => $events]);
    }

    private static function getEnergyReport(): void {
        $data = ReportService::getEnergyReport();
        echo json_encode(['success' => true, 'energy' => $data]);
    }

    private static function getMaintenanceKpis(): void {
        $data = ReportService::getMaintenanceKpis();
        echo json_encode(['success' => true, 'kpis' => $data]);
    }

    private static function exportEnergyCsv(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gandhidham_energy_report_' . date('Ymd_His') . '.csv"');
        echo ReportService::generateEnergyCsv();
        exit;
    }

    private static function exportWorkOrdersCsv(): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gandhidham_work_orders_' . date('Ymd_His') . '.csv"');
        echo ReportService::generateWorkOrdersCsv();
        exit;
    }

    private static function simulateReading(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $feederId = (int)($input['feeder_id'] ?? 1);
        $vR = (float)($input['v_r'] ?? 230.5);
        $powerKw = (float)($input['power_kw'] ?? 5.4);

        $now = date('Y-m-d H:i:s');
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE device_latest_state 
            SET voltage_v = :v, power_kw = :kw, last_seen_at = :now, is_online = 1
            WHERE asset_type = 'feeder' AND asset_id = :fid
        ");
        $stmt->execute([':v' => $vR, ':kw' => $powerKw, ':fid' => $feederId, ':now' => $now]);

        echo json_encode(['success' => true, 'message' => 'Simulated telemetry ingested successfully']);
    }
}
