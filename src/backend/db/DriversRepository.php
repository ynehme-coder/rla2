<?php
require_once __DIR__ . '/Database.php';

class DriversRepository {
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM drivers');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM drivers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findAvailableDrivers(): array
    {
        $sql = <<<SQL
SELECT d.*
FROM drivers d
LEFT JOIN delivery_assignments a
  ON a.driver_id = d.id AND a.assignment_status IN ('assigned','started')
WHERE a.id IS NULL
SQL;
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

}
