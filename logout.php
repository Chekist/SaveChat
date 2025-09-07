<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';

$session = SessionManager::getInstance();

// Безопасный выход
$session->destroy();

// Безопасная очистка cookies
// amazonq-ignore-next-line
require_once __DIR__ . '/SecurityHelper.php';
SecurityHelper::clearCookiesSafely();

header('Location: /?page=home');
exit;
?>