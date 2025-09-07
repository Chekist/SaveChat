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
    $groupName = Validator::sanitizeString($_POST['group_name'] ?? '', 100);
    $currentUserId = $session->getUserId();
    
    if (empty($groupName)) {
        exit(json_encode(['success' => false, 'message' => 'Название группы обязательно']));
    }
    
    $db = LocalDatabase::getInstance();
    $chatId = 'group_' . uniqid();
    
    $db->getConnection()->beginTransaction();
    
    // Создаем запись в чате
    $stmt = $db->getConnection()->prepare("
        INSERT INTO chat (chat_id, type) 
        VALUES (?, 'group')
    ");
    $stmt->execute([$chatId]);
    
    // Добавляем создателя как администратора
    $stmt = $db->getConnection()->prepare("
        INSERT INTO chat_members (chat_id, user_id, role) 
        VALUES (?, ?, 'admin')
    ");
    $stmt->execute([$chatId, $currentUserId]);
    
    // Создаем настройки группы
    $stmt = $db->getConnection()->prepare("
        INSERT INTO group_settings (chat_id, name) 
        VALUES (?, ?)
    ");
    $stmt->execute([$chatId, $groupName]);
    
    // Системное сообщение о создании
    $stmt = $db->getConnection()->prepare("
        INSERT INTO messages (user_id, chat_id, text, msg_type) 
        VALUES (?, ?, ?, 'system')
    ");
    $stmt->execute([$currentUserId, $chatId, "Группа '$groupName' создана"]);
    
    $db->getConnection()->commit();
    
    exit(json_encode(['success' => true, 'chat_id' => $chatId]));
    
} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }
    error_log('Group creation error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
?>