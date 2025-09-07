<?php
/**
 * helper.php
 * Утилиты для генерации и проверки токенов безопасности.
 *  - getHmacKey()            – берёт ключ из переменной окружения
 *  - makeToken()             – создаёт token (time.sig)
 *  - verifyToken()           – проверяет token (TTL 5 мин, привязка к session_id + user_agent)
 */

declare(strict_types=1);

function getHmacKey(): string
{
    // Ключ должен быть задан в .env
    $key = $_ENV['HMAC_KEY'] ?? '';
    if ($key === '') {
        throw new RuntimeException('HMAC_KEY не задан');
    }
    return $key;
}

/**
 * Создаёт токен:  time . '.' . sig
 */
function makeToken(): array
{
    $time  = time();
    $raw   = $time . '|' . session_id() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $sig   = hash_hmac('sha256', $raw, getHmacKey());
    return ['token' => $time . '.' . $sig, 'time' => $time];
}

/**
 * Проверяет токен
 */
function verifyToken(string $token): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return false;
    }

    [$timeStr, $sigReceived] = $parts;
    if (!ctype_digit($timeStr)) {
        return false;
    }

    // TTL 300 сек
    if (abs(time() - (int)$timeStr) > 300) {
        return false;
    }

    $raw = $timeStr . '|' . session_id() . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $sigExpected = hash_hmac('sha256', $raw, getHmacKey());

    return hash_equals($sigExpected, $sigReceived);
}