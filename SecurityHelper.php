<?php
/**
 * Вспомогательный класс для безопасности
 */
class SecurityHelper {
    
    public static function clearCookiesSafely() {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
            foreach($cookies as $cookie) {
                $parts = explode('=', $cookie);
                $name = trim($parts[0]);
                setcookie($name, '', time()-1000);
                setcookie($name, '', time()-1000, '/');
            }
        }
    }
    
    public static function generateCSRFToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Удаляем старый токен, если он существует дольше 1 часа
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Проверяем, не истекло ли время жизни токена (1 час)
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            self::clearCSRFToken();
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        // После успешной проверки генерируем новый токен для следующего запроса
        if ($isValid) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $isValid;
    }
    
    public static function clearCSRFToken(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    }
    
    public static function sanitizeOutput(string $data): string {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    public static function escapeHtml(string $data): string {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateSecureFilename(string $originalName): string {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
        return uniqid() . '_' . substr($safeName, 0, 50) . '.' . $extension;
    }
    
    /**
     * Простая эвристическая проверка входных данных на подозрительные конструкции (XSS/SQLi)
     */
    public static function detectSuspiciousActivity(string $input): bool {
        $normalized = strtolower($input);
        
        // Быстрые подстроки
        $needles = [
            '<script', 'javascript:', 'onerror=', 'onload=', 'onmouseover=', 'src=', 'data:',
            'union select', 'drop table', 'insert into', 'xp_', '--', ';--', ' or ', "' or ", '1=1',
            '../', '..\\', '%3cscript', '%3c%2fscript%3e'
        ];
        foreach ($needles as $n) {
            if (strpos($normalized, $n) !== false) {
                return true;
            }
        }
        
        // Регулярные выражения для более сложных случаев
        $patterns = [
            '/<\s*script\b/i',
            '/on[a-z]+\s*=\s*/i',
            '/javascript\s*:/i',
            '/union\s+all?\s+select/i',
            '/(?:\b|%27)(?:or|and)\b\s+\d+\s*=\s*\d+/i',
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Логирование подозрительной активности в системный лог
     */
    public static function logSuspiciousActivity(string $type, string $payload, array $context = []): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $uid = $_SESSION['user_id'] ?? null;
        
        $entry = [
            'type' => $type,
            'ip' => $ip,
            'user_agent' => $ua,
            'uri' => $uri,
            'referer' => $ref,
            'user_id' => $uid,
            'payload' => $payload,
            'context' => $context,
            'time' => date('c'),
        ];
        
        // Пишем в error_log в формате JSON
        error_log('[SECURITY] ' . json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Stateless XSRF token (Double Submit Cookie with HMAC signature)
     * Cookie: XSRF-TOKEN (readable by JS)
     * Header: X-CSRF-Token
     */
    public static function ensureXsrfCookie(int $ttlSeconds = 7200): void {
        $cookieName = 'XSRF-TOKEN';
        $token = $_COOKIE[$cookieName] ?? '';
        if ($token && self::isValidSignedToken($token, $ttlSeconds)) {
            return;
        }
        $newToken = self::generateSignedToken();
        // Cookie should be readable by JS for double-submit pattern (httponly = false)
        setcookie(
            $cookieName,
            $newToken,
            [
                'expires' => time() + $ttlSeconds,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => false,
                'samesite' => 'Strict',
            ]
        );
        $_COOKIE[$cookieName] = $newToken; // make it available immediately in the same request
    }

    public static function getXsrfCookie(): ?string {
        return $_COOKIE['XSRF-TOKEN'] ?? null;
    }

    public static function validateXsrf(?string $headerToken, int $ttlSeconds = 7200): bool {
        $cookieToken = self::getXsrfCookie();
        if (empty($cookieToken) || empty($headerToken)) return false;
        // Both must be equal and individually valid (signature + ttl)
        if (!hash_equals($cookieToken, $headerToken)) return false;
        return self::isValidSignedToken($headerToken, $ttlSeconds);
    }

    private static function generateSignedToken(): string {
        $nonce = bin2hex(random_bytes(16));
        $ts = (string)time();
        $secret = self::getAppSecret();
        $sig = hash_hmac('sha256', $nonce . '|' . $ts, $secret);
        return base64_encode($nonce . '|' . $ts . '|' . $sig);
    }

    private static function isValidSignedToken(string $token, int $ttlSeconds): bool {
        $decoded = base64_decode($token, true);
        if ($decoded === false) return false;
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) return false;
        [$nonce, $ts, $sig] = $parts;
        if (!ctype_xdigit($nonce) || strlen($nonce) !== 32) return false;
        if (!ctype_digit($ts)) return false;
        if ((time() - (int)$ts) > $ttlSeconds) return false;
        $secret = self::getAppSecret();
        $expected = hash_hmac('sha256', $nonce . '|' . $ts, $secret);
        return hash_equals($expected, $sig);
    }

    private static function getAppSecret(): string {
        // Reuse encryption key as an application secret
        if (!class_exists('Config')) {
            require_once __DIR__ . '/Config.php';
        }
        return Config::getEncryptionKey();
    }
}
?>