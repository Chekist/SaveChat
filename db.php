<?php
require_once __DIR__ . '/Config.php';

// Безопасная конфигурация базы данных
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        Config::load();
        $config = Config::getDbConfig();
        $host = $config['host'];
        $dbname = $config['name'];
        $username = $config['user'];
        $password = $config['pass'];
        
        try {
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch(PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            try {
                self::$instance = new self();
            } catch (Exception $e) {
                error_log('Failed to create database instance: ' . $e->getMessage());
                throw $e;
            }
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

$db = Database::getInstance()->getConnection();
?>