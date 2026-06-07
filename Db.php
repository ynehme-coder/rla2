<?php
class Db {
    private const HOST     = 'localhost';
    private const DB_NAME  = 'rla_medical_delivery'; // Change if your DB name differs
    private const USERNAME = 'root';
    private const PASSWORD = '';
    private static ?PDO $con = null;

    public static function getConnection(): ?PDO
    {
        $dsn = "mysql:host=" . self::HOST . ";dbname=" . self::DB_NAME;
        try {
            self::$con = new PDO($dsn, self::USERNAME, self::PASSWORD);
            self::$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return self::$con;
        } catch (PDOException $ex) {
            echo $ex->getMessage();
        }
        return null;
    }

    public static function closeConnection(): void
    {
        self::$con = null;
    }
}