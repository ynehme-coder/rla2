<?php
require_once __DIR__ . '/Database.php';

class AssignmentsRepository {
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createAssignment(int $deliveryId, int $driverId, int $vehicleId, ?string $scheduledStart = null, ?string $scheduledEnd = null, string $status = 'assigned'): int
    {
        $stmt = $this->db->prepare('INSERT INTO delivery_assignments (delivery_id, driver_id, vehicle_id, scheduled_start, scheduled_end, assignment_status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$deliveryId, $driverId, $vehicleId, $scheduledStart, $scheduledEnd, $status]);
        return (int)$this->db->lastInsertId();
    }

    public function markCompleted(int $assignmentId): bool
    {
        $stmt = $this->db->prepare('UPDATE delivery_assignments SET assignment_status = ? WHERE id = ?');
        return $stmt->execute(['completed', $assignmentId]);
    }

    public function getAssignmentsByDriver(int $driverId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM delivery_assignments WHERE driver_id = ? ORDER BY assigned_at DESC');
        $stmt->execute([$driverId]);
        return $stmt->fetchAll();
    }

    public function getActiveAssignmentByDeliveryId(int $deliveryId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM delivery_assignments WHERE delivery_id = ? AND assignment_status IN (\'assigned\', \'started\') ORDER BY assigned_at DESC LIMIT 1');
        $stmt->execute([$deliveryId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function countActiveAssignmentsByVehicle(int $vehicleId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM delivery_assignments WHERE vehicle_id = ? AND assignment_status IN (\'assigned\', \'started\')');
        $stmt->execute([$vehicleId]);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    public function cancelActiveAssignmentsByDeliveryId(int $deliveryId): int
    {
        $stmt = $this->db->prepare('UPDATE delivery_assignments SET assignment_status = \'cancelled\' WHERE delivery_id = ? AND assignment_status IN (\'assigned\', \'started\')');
        $stmt->execute([$deliveryId]);
        return $stmt->rowCount();
    }

    public function createDeliveryGroup(string $name, array $deliveryIds): int
    {
        $stmt = $this->db->prepare('INSERT INTO delivery_groups (name, created_at) VALUES (?, NOW())');
        $stmt->execute([$name]);
        $groupId = (int)$this->db->lastInsertId();

        $linkStmt = $this->db->prepare('INSERT INTO delivery_group_items (group_id, delivery_id) VALUES (?, ?)');
        foreach ($deliveryIds as $deliveryId) {
            $linkStmt->execute([$groupId, (int)$deliveryId]);
        }

        return $groupId;
    }
}
