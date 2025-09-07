<?php
/**
 * Безопасная конфигурация приложения
 */
class Config {
    private static $config = null;
    
    public static function load() {
        if (self::$config === null) {
            $envFile = __DIR__ . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $_ENV[trim($key)] = trim($value);
                    }
                }
            }
            self::$config = true;
        }
    }
    
    public static function get($key, $default = null) {
        self::load();
        return $_ENV[$key] ?? $default;
    }
    
    public static function getDbConfig() {
        return [
            'host' => self::get('DB_HOST', '127.0.0.1'),
            'name' => self::get('DB_NAME', 'p2p_calls'),
            'user' => self::get('DB_USER', 'root'),
            'pass' => self::get('DB_PASS', ''),
        ];
    }
    
    public static function getEncryptionKey() {
        $key = self::get('DB_ENCRYPTION_KEY');
        if (empty($key) || $key === 'your-32-byte-encryption-key-here-change-this') {
            throw new RuntimeException('Encryption key not configured properly');
        }
        return hash('sha256', $key, true);
    }
    
    public static function getPasswordSalt() {
        $salt = self::get('PASSWORD_SALT');
        if (empty($salt) || $salt === 'your-unique-password-salt-here-change-this') {
            throw new RuntimeException('Password salt not configured properly');
        }
        return $salt;
    }
}
?>