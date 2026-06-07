<?php
require_once __DIR__ . '/Database.php';

class VehiclesRepository {
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $sql = 'SELECT v.*, vt.capacity_kg, vt.capacity_m3, vt.max_range_km FROM vehicles v LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT v.*, vt.capacity_kg, vt.capacity_m3, vt.max_range_km FROM vehicles v LEFT JOIN vehicle_types vt ON vt.id = v.vehicle_type_id WHERE v.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findAvailableVehicles(float $minCapacityKg = 0, bool $needsCooling = false): array
    {
        $sql = 'SELECT v.*, vt.capacity_kg, vt.capacity_m3, vt.max_range_km FROM vehicles v JOIN vehicle_types vt ON vt.id = v.vehicle_type_id WHERE v.status = "available" AND vt.capacity_kg >= ?';
        if ($needsCooling) {
            $sql .= ' AND v.refrigerated = 1';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$minCapacityKg]);
        return $stmt->fetchAll();
    }
}
