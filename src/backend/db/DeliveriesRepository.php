<?php
require_once __DIR__ . '/Database.php';

class DeliveriesRepository {
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM deliveries ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();
        
        foreach ($rows as &$r) {
            $r['items'] = $this->getDeliveryItems((int)$r['id']);
        }
        
        return $rows;
    }
    public function getPendingDeliveries(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM deliveries WHERE status = ? ORDER BY priority ASC, deadline ASC');
        $stmt->execute(['pending']);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['items'] = $this->getDeliveryItems((int)$r['id']);
        }

        return $rows;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM deliveries WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) return null;
        $row['items'] = $this->getDeliveryItems((int)$row['id']);
        return $row;
    }

    public function getDeliveryItems(int $deliveryId): array
    {
        $stmt = $this->db->prepare('SELECT di.*, p.name, p.weight_kg, p.volume_m3 FROM delivery_items di LEFT JOIN products p ON p.id = di.product_id WHERE di.delivery_id = ?');
        $stmt->execute([$deliveryId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $deliveryId, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE deliveries SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $deliveryId]);
    }
}
