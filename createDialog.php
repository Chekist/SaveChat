<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $userId = Validator::validateInteger($_POST['user_id'] ?? 0, 1);
    $currentUserId = $session->getUserId();
    
    if ($userId === $currentUserId) {
        exit(json_encode(['success' => false, 'message' => 'Нельзя создать диалог с самим собой']));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Проверяем существование пользователя
    $stmt = $db->getConnection()->prepare("SELECT id FROM user WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        exit(json_encode(['success' => false, 'message' => 'Пользователь не найден']));
    }
    
    // Проверяем существующий диалог
    $stmt = $db->getConnection()->prepare("
        SELECT chat_id FROM chat 
        WHERE type = 'dialog' 
        AND ((user_id1 = ? AND user_id2 = ?) OR (user_id1 = ? AND user_id2 = ?))
    ");
    $stmt->execute([$currentUserId, $userId, $userId, $currentUserId]);
    $existingChat = $stmt->fetch();
    
    if ($existingChat) {
        $session->set('active_chat', $existingChat['chat_id']);
        exit(json_encode(['success' => true, 'message' => 'Диалог уже существует']));
    }
    
    // Создаем новый диалог
    $chatId = 'dialog_' . min($currentUserId, $userId) . '_' . max($currentUserId, $userId);
    
    $stmt = $db->getConnection()->prepare("
        INSERT INTO chat (chat_id, type, user_id1, user_id2) 
        VALUES (?, 'dialog', ?, ?)
    ");
    $stmt->execute([$chatId, $currentUserId, $userId]);
    
    $session->set('active_chat', $chatId);
    exit(json_encode(['success' => true, 'message' => 'Диалог создан']));
    
} catch (Exception $e) {
    error_log('Dialog creation error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Ошибка создания диалога']));
}
?>