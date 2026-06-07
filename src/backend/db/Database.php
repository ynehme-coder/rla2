<?php
class Database {
    private static ?PDO $conn = null;

    public static function getConnection(): PDO
    {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db   = getenv('DB_NAME') ?: 'rla_medical_delivery';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $port = getenv('DB_PORT') ?: '3306';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$conn = new PDO($dsn, $user, $pass, $options);
            return self::$conn;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function beginTransaction(): void
    {
        $db = self::getConnection();
        $db->beginTransaction();
    }

    public static function commit(): void
    {
        $db = self::getConnection();
        $db->commit();
    }

    public static function rollback(): void
    {
        $db = self::getConnection();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}
