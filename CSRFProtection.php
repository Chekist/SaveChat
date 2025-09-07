<?php
/**
 * Защита от CSRF атак
 */
class CSRFProtection {
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LIFETIME = 300; // 5 минут
    
    public static function generateToken(): string {
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'timestamp' => $timestamp
        ];
        
        return $token;
    }
    
    public static function validateToken(string $token): bool {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        $storedData = $_SESSION[self::TOKEN_NAME];
        
        // Проверка времени жизни токена
        if (time() - $storedData['timestamp'] > self::TOKEN_LIFETIME) {
            unset($_SESSION[self::TOKEN_NAME]);
            return false;
        }
        
        // Проверка токена
        $isValid = hash_equals($storedData['token'], $token);
        
        if ($isValid) {
            // Удаляем использованный токен
            unset($_SESSION[self::TOKEN_NAME]);
        }
        
        return $isValid;
    }
    
    public static function getTokenInput(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    public static function requireValidToken(): void {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        
        if (!self::validateToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            exit(json_encode(['error' => 'Invalid CSRF token']));
        }
    }
}
?>