<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false]));
}

try {
    $chatId = $_POST['chat_id'] ?? '';
    $userId = $session->getUserId();
    
    if (empty($chatId)) {
        exit(json_encode(['success' => false]));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Помечаем все сообщения в чате как прочитанные для текущего пользователя
    $stmt = $db->getConnection()->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE chat_id = ? AND user_id != ? AND is_read = 0
    ");
    $stmt->execute([$chatId, $userId]);
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    error_log('Mark as read error: ' . $e->getMessage());
    exit(json_encode(['success' => false]));
}
?>