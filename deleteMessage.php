<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $currentUserId = $session->getUserId();
    
    if (!$messageId) {
        exit(json_encode(['success' => false, 'message' => 'Неверный ID сообщения']));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Проверяем что сообщение принадлежит пользователю
    $stmt = $db->getConnection()->prepare("SELECT user_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message || $message['user_id'] != $currentUserId) {
        exit(json_encode(['success' => false, 'message' => 'Нет прав на удаление']));
    }
    
    // Удаляем сообщение
    $stmt = $db->getConnection()->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    error_log('Delete message error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Ошибка удаления']));
}
?>