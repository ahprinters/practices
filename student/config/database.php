<?php
namespace Student\Config;

class Database {
    private static $host = 'localhost';
    private static $dbName = 'student_management';
    private static $username = 'root';
    private static $password = '';
    private static $charset = 'utf8mb4';
    private static $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    public static function getConnection() {
        $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbName . ";charset=" . self::$charset;
        
        try {
            return new \PDO($dsn, self::$username, self::$password, self::$options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}
?>