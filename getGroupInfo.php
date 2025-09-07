<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode([]));
}

try {
    $chatId = $_POST['chat_id'] ?? '';
    
    if (empty($chatId)) {
        exit(json_encode([]));
    }
    
    $db = LocalDatabase::getInstance();
    
    $stmt = $db->getConnection()->prepare("SELECT name, avatar FROM group_settings WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $info = $stmt->fetch();
    
    exit(json_encode($info ?: []));
    
} catch (Exception $e) {
    error_log('Get group info error: ' . $e->getMessage());
    exit(json_encode([]));
}
?>