<?php
// Maintenance Work Orders & Repair Verification Service
namespace App\Services;

use App\Database;
use PDO;

class WorkOrderService {
    /**
     * Fetch list of work orders with assignee and asset context
     */
    public static function getWorkOrders(?string $status = null, ?int $assignedTo = null): array {
        $db = Database::getConnection();
        $sql = "
            SELECT wo.*, u.full_name as assigned_name, u.username as assigned_username,
                   a.fault_code, a.severity, a.summary as alert_summary
            FROM work_orders wo
            LEFT JOIN users u ON wo.assigned_to = u.id
            LEFT JOIN alerts a ON wo.alert_id = a.id
        ";
        $conditions = [];
        $params = [];

        if ($status && $status !== 'all') {
            $conditions[] = "wo.status = :status";
            $params[':status'] = $status;
        }
        if ($assignedTo) {
            $conditions[] = "wo.assigned_to = :assigned_to";
            $params[':assigned_to'] = $assignedTo;
        }

        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY wo.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Generate a new Work Order directly from an alert
     */
    public static function createFromAlert(int $alertId, ?int $assignedTo, string $priority, ?string $customTitle, int $userId): array {
        $db = Database::getConnection();

        // Fetch alert
        $stmt = $db->prepare("SELECT * FROM alerts WHERE id = :id");
        $stmt->execute([':id' => $alertId]);
        $alert = $stmt->fetch();
        if (!$alert) {
            throw new \InvalidArgumentException("Alert #{$alertId} not found");
        }

        // SLA deadline calculation based on priority
        $slaHours = match($priority) {
            'emergency' => 4,
            'high' => 12,
            'medium' => 24,
            'low' => 72,
            default => 24
        };
        $slaDeadline = date('Y-m-d H:i:s', time() + ($slaHours * 3600));

        $orderCode = 'WO-' . date('Ym') . '-' . str_pad((string)$alertId, 4, '0', STR_PAD_LEFT);
        $title = $customTitle ?: "Repair: {$alert['summary']}";
        $assignee = $assignedTo ?: 4; // Default to Apex Electrical Maintenance Contractor if unassigned

        $insertStmt = $db->prepare("
            INSERT INTO work_orders 
            (order_code, alert_id, asset_type, asset_id, title, priority, assigned_to, sla_deadline, status, created_at, updated_at)
            VALUES (:code, :alert_id, :asset_type, :asset_id, :title, :priority, :assigned_to, :sla, 'assigned', NOW(), NOW())
        ");
        $insertStmt->execute([
            ':code' => $orderCode,
            ':alert_id' => $alertId,
            ':asset_type' => $alert['asset_type'],
            ':asset_id' => $alert['asset_id'],
            ':title' => $title,
            ':priority' => $priority,
            ':assigned_to' => $assignee,
            ':sla' => $slaDeadline
        ]);
        $newId = (int)$db->lastInsertId();

        // Log timeline event
        self::logEvent($newId, $userId, 'open', 'assigned', "Work order generated from Alert #{$alertId} with {$slaHours}h SLA");

        // Update alert status to acknowledged
        $db->prepare("
            UPDATE alerts 
            SET status = 'acknowledged', acknowledged_by = :uid, acknowledged_at = NOW()
            WHERE id = :aid AND status = 'active'
        ")->execute([':uid' => $userId, ':aid' => $alertId]);

        AuthService::logAudit($userId, 'CREATE_WORK_ORDER', 'work_orders', (string)$newId, [
            'order_code' => $orderCode,
            'alert_id' => $alertId,
            'priority' => $priority
        ]);

        return [
            'id' => $newId,
            'order_code' => $orderCode,
            'status' => 'assigned',
            'sla_deadline' => $slaDeadline
        ];
    }

    /**
     * Update work order status and attach repair evidence notes
     */
    public static function updateStatus(int $workOrderId, string $newStatus, ?string $notes, ?string $evidenceNotes, int $userId): bool {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT * FROM work_orders WHERE id = :id");
        $stmt->execute([':id' => $workOrderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return false;
        }

        $fromStatus = $order['status'];
        $updateSql = "UPDATE work_orders SET status = :status, updated_at = NOW()";
        $params = [':status' => $newStatus, ':id' => $workOrderId];

        if ($evidenceNotes !== null) {
            $updateSql .= ", repair_evidence_notes = :notes";
            $params[':notes'] = $evidenceNotes;
        }

        if ($newStatus === 'closed') {
            $updateSql .= ", closed_at = NOW()";
        }

        $updateSql .= " WHERE id = :id";
        $db->prepare($updateSql)->execute($params);

        self::logEvent($workOrderId, $userId, $fromStatus, $newStatus, $notes ?: "Status changed to {$newStatus}");

        // If closed, resolve the associated alert as well
        if ($newStatus === 'closed' && !empty($order['alert_id'])) {
            $db->prepare("
                UPDATE alerts 
                SET status = 'resolved', resolved_by = :uid, resolved_at = NOW(), resolution_notes = :rnotes
                WHERE id = :aid AND status != 'resolved'
            ")->execute([
                ':uid' => $userId,
                ':rnotes' => "Closed via Work Order {$order['order_code']}: " . ($evidenceNotes ?: 'Repair verified'),
                ':aid' => $order['alert_id']
            ]);
        }

        AuthService::logAudit($userId, 'UPDATE_WORK_ORDER', 'work_orders', (string)$workOrderId, [
            'from' => $fromStatus,
            'to' => $newStatus
        ]);

        return true;
    }

    /**
     * Log an audit event in work_order_events
     */
    public static function logEvent(int $workOrderId, int $userId, string $fromStatus, string $toStatus, ?string $notes): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO work_order_events (work_order_id, user_id, from_status, to_status, notes, created_at)
            VALUES (:wo_id, :uid, :from_s, :to_s, :notes, NOW())
        ");
        $stmt->execute([
            ':wo_id' => $workOrderId,
            ':uid' => $userId,
            ':from_s' => $fromStatus,
            ':to_s' => $toStatus,
            ':notes' => $notes
        ]);
    }

    /**
     * Fetch timeline history for a work order
     */
    public static function getEvents(int $workOrderId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT e.*, u.full_name as user_name
            FROM work_order_events e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.work_order_id = :wo_id
            ORDER BY e.id ASC
        ");
        $stmt->execute([':wo_id' => $workOrderId]);
        return $stmt->fetchAll();
    }
}
