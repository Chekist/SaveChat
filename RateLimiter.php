<?php
declare(strict_types=1);

/**
 * Класс для ограничения количества запросов (защита от брутфорса и спама)
 */
class RateLimiter {
    private string $action;
    private int $maxAttempts;
    private int $timeWindow;
    private string $storageKey;
    
    /**
     * @param string $action Название действия для ограничения (например, 'login_attempts')
     * @param int $maxAttempts Максимальное количество попыток
     * @param int $timeWindow Временное окно в секундах
     */
    public function __construct(string $action, int $maxAttempts = 5, int $timeWindow = 300) {
        $this->action = $action;
        $this->maxAttempts = $maxAttempts;
        $this->timeWindow = $timeWindow;
        $this->storageKey = 'rate_limit_' . $action;
        
        $this->initSession();
    }
    
    private function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[$this->storageKey]) || !is_array($_SESSION[$this->storageKey])) {
            $_SESSION[$this->storageKey] = [];
        }
    }
    
    /**
     * Получает идентификатор клиента на основе IP и User-Agent
     */
    private function getClientIdentifier(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        
        // Учитываем X-Forwarded-For, если есть прокси
        $identifier = $ip . '|' . $forwardedFor . '|' . $userAgent;
        return hash('sha256', $identifier);
    }
    
    /**
     * Получает данные о лимитах для текущего клиента
     */
    private function getClientData(string $identifier): array {
        if (!isset($_SESSION[$this->storageKey][$identifier])) {
            return [
                'attempts' => 0,
                'first_attempt' => time(),
                'last_attempt' => 0,
                'locked_until' => 0
            ];
        }
        
        return $_SESSION[$this->storageKey][$identifier];
    }
    
    /**
     * Сохраняет данные о лимитах для текущего клиента
     */
    private function saveClientData(string $identifier, array $data): void {
        $_SESSION[$this->storageKey][$identifier] = $data;
    }
    
    /**
     * Проверяет, превышен ли лимит запросов для текущего клиента
     * 
     * @return bool True если лимит превышен, иначе false
     */
    public function isRateLimited(string $identifier = null): bool {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $data = $this->getClientData($identifier);
        $now = time();
        
        // Проверяем блокировку
        if ($data['locked_until'] > $now) {
            return true;
        }
        
        // Сбрасываем счетчик, если истекло временное окно
        if (($now - $data['first_attempt']) > $this->timeWindow) {
            $data['attempts'] = 0;
            $data['first_attempt'] = $now;
        }
        
        return false;
    }
    
    /**
     * Увеличивает счетчик попыток и применяет блокировку при необходимости
     * 
     * @return bool True если лимит не превышен, false если превышен
     */
    public function attempt(string $identifier = null): bool {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $data = $this->getClientData($identifier);
        $now = time();
        
        // Сбрасываем счетчик, если истекло временное окно
        if (($now - $data['first_attempt']) > $this->timeWindow) {
            $data['attempts'] = 0;
            $data['first_attempt'] = $now;
        }
        
        // Увеличиваем счетчик попыток
        $data['attempts']++;
        $data['last_attempt'] = $now;
        
        // Проверяем, не превышен ли лимит
        if ($data['attempts'] > $this->maxAttempts) {
            // Блокируем на время, кратное количеству превышений
            $excessAttempts = $data['attempts'] - $this->maxAttempts;
            $lockoutTime = min(3600, $this->timeWindow * pow(2, $excessAttempts - 1));
            $data['locked_until'] = $now + $lockoutTime;
            
            $this->saveClientData($identifier, $data);
            return false;
        }
        
        $this->saveClientData($identifier, $data);
        return true;
    }
    
    /**
     * Возвращает оставшееся время блокировки в секундах
     */
    public function getRemainingTime(string $identifier = null): int {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $data = $this->getClientData($identifier);
        $now = time();
        
        if ($data['locked_until'] <= $now) {
            return 0;
        }
        
        return $data['locked_until'] - $now;
    }
    
    /**
     * Сбрасывает счетчик попыток для указанного идентификатора
     */
    public function reset(string $identifier = null): void {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        if (isset($_SESSION[$this->storageKey][$identifier])) {
            unset($_SESSION[$this->storageKey][$identifier]);
        }
    }
    
    /**
     * Очищает устаревшие записи
     */
    public function cleanup(): void {
        if (!isset($_SESSION[$this->storageKey]) || !is_array($_SESSION[$this->storageKey])) {
            return;
        }
        
        $now = time();
        foreach ($_SESSION[$this->storageKey] as $identifier => $data) {
            // Удаляем записи старше 24 часов
            if (($now - max($data['last_attempt'], $data['locked_until'])) > 86400) {
                unset($_SESSION[$this->storageKey][$identifier]);
            }
        }
    }
    
    /**
     * Статический метод для быстрой проверки лимита
     */
    /**
     * Статический метод для быстрой проверки лимита
     */
    public static function checkLimit(string $action, int $maxAttempts = 5, int $timeWindow = 300): bool {
        $limiter = new self($action, $maxAttempts, $timeWindow);
        return $limiter->attempt();
    }
    
    /**
     * Записывает попытку доступа
     */
    public static function recordAttempt(string $action, ?string $identifier = null): void {
        $limiter = new self($action);
        $limiter->attempt($identifier);
    }
    
    /**
     * Проверяет лимит и выбрасывает исключение, если он превышен
     * @throws RateLimitExceededException Если лимит запросов превышен
     */
    /**
     * Проверяет лимит и выбрасывает исключение, если он превышен
     * @throws RateLimitExceededException Если лимит запросов превышен
     */
    public static function requireLimit(string $action, ?string $identifier = null): void {
        $limiter = new self($action);
        if ($limiter->isRateLimited($identifier)) {
            $remainingTime = $limiter->getRemainingTime($identifier);
            throw new RateLimitExceededException(
                "Слишком много запросов. Пожалуйста, попробуйте снова через {$remainingTime} секунд.",
                $remainingTime
            );
        }
        $limiter->attempt($identifier);
    }
    
    /**
     * Возвращает оставшееся время блокировки в секундах (статический метод)
     */
    public static function getRemainingTimeStatic(string $action, ?string $identifier = null): int {
        $limiter = new self($action);
        return $limiter->getRemainingTime($identifier);
    }
}

/**
 * Исключение, выбрасываемое при превышении лимита запросов
 */
class RateLimitExceededException extends Exception {
    private int $retryAfter;
    
    public function __construct(string $message = "", int $retryAfter = 0, int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
    }
    
    public function getRetryAfter(): int {
        return $this->retryAfter;
    }
}
?>