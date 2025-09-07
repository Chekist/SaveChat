<?php
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
if (!$session->isLoggedIn()) {
    exit;
}

$db = LocalDatabase::getInstance();
$userId = $session->getUserId();

// Обновляем время последней активности
$stmt = $db->getConnection()->prepare("UPDATE user SET last_activity = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$userId]);

header('Content-Type: application/json');
exit(json_encode(['success' => true]));
?>