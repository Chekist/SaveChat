<?php
declare(strict_types=1);

class SessionManager {
    private static ?self $instance = null;
    private const SESSION_TIMEOUT = 1800; // 30 минут
    private const USER_AGENT_KEY = 'session_user_agent';
    private const LAST_ACTIVITY_KEY = 'session_last_activity';

    private function __construct() {
        // Приватный конструктор для синглтона
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initSession();
        }
        return self::$instance;
    }

    private function initSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        // Имя cookie выравниваем с тем, что уже присылает браузер (PHPSESSID),
        // чтобы сервер видел ту же сессию и CSRF-токен совпадал
        session_name('PHPSESSID');
        session_set_cookie_params([
            'lifetime' => 0, // до закрытия браузера
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start();

        if (!isset($_SESSION[self::LAST_ACTIVITY_KEY])) {
            $this->initializeSessionState();
        } else {
            $this->validateSession();
        }
    }

    private function initializeSessionState(): void {
        $_SESSION[self::LAST_ACTIVITY_KEY] = time();
        $_SESSION[self::USER_AGENT_KEY] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    private function validateSession(): void {
        if (isset($_SESSION[self::LAST_ACTIVITY_KEY]) && (time() - $_SESSION[self::LAST_ACTIVITY_KEY]) > self::SESSION_TIMEOUT) {
            $this->destroy();
            return;
        }

        if (isset($_SESSION[self::USER_AGENT_KEY]) && $_SESSION[self::USER_AGENT_KEY] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            $this->destroy();
            return;
        }

        $_SESSION[self::LAST_ACTIVITY_KEY] = time();
    }

    public function login(int $userId): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $this->initializeSessionState();
    }

    public function logout(): void {
        $this->destroy();
        header('Location: /?page=login');
        exit;
    }

    public function destroy(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }

    public function isLoggedIn(): bool {
        $this->validateSession();
        return isset($_SESSION['user_id']);
    }

    public function getUserId(): ?int {
        return $this->isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Обратная совместимость: требовать аутентификацию для доступа к ресурсам API.
     * Если пользователь не авторизован — возвращаем 401 JSON-ответ и завершаем выполнение.
     */
    public function requireAuth(): void {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }

    /**
     * Обратная совместимость: установить произвольное значение в сессию
     */
    public function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    /**
     * Обратная совместимость: получить значение из сессии
     */
    public function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Обратная совместимость: проверить наличие ключа в сессии
     */
    public function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    /**
     * Обратная совместимость: удалить ключ из сессии
     */
    public function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    /**
     * Обратная совместимость: установить ID пользователя (проксирует на login)
     */
    public function setUserId(int $userId): void {
        $this->login($userId);
    }
}