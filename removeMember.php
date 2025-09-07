<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $chatId = Validator::sanitizeString($_POST['chat_id'] ?? '', 100);
    $userId = Validator::validateInteger($_POST['user_id'] ?? 0, 1);
    $currentUserId = $session->getUserId();
    
    $db = LocalDatabase::getInstance();
    
    // Проверяем, что текущий пользователь админ группы
    $stmt = $db->getConnection()->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $currentUserId]);
    $currentMember = $stmt->fetch();
    
    if (!$currentMember || $currentMember['role'] !== 'admin') {
        exit(json_encode(['success' => false, 'message' => 'Только администраторы могут удалять участников']));
    }
    
    // Проверяем, что удаляемый пользователь существует в группе
    $stmt = $db->getConnection()->prepare("SELECT u.login FROM chat_members cm JOIN user u ON cm.user_id = u.id WHERE cm.chat_id = ? AND cm.user_id = ?");
    $stmt->execute([$chatId, $userId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        exit(json_encode(['success' => false, 'message' => 'Пользователь не найден в группе']));
    }
    
    // Нельзя удалить самого себя
    if ($userId === $currentUserId) {
        exit(json_encode(['success' => false, 'message' => 'Нельзя удалить самого себя']));
    }
    
    $db->getConnection()->beginTransaction();
    
    // Удаляем участника
    $stmt = $db->getConnection()->prepare("DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $userId]);
    
    // Системное сообщение
    $stmt = $db->getConnection()->prepare("
        INSERT INTO messages (user_id, chat_id, text, msg_type) 
        VALUES (?, ?, ?, 'system')
    ");
    $stmt->execute([$currentUserId, $chatId, "{$member['login']} исключен из группы"]);
    
    $db->getConnection()->commit();
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    error_log('Remove member error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Ошибка удаления участника']));
}
?>