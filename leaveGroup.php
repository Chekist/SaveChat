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
    $currentUserId = $session->getUserId();
    
    $db = LocalDatabase::getInstance();
    
    // Проверяем, что пользователь участник группы
    $stmt = $db->getConnection()->prepare("SELECT role FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $currentUserId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        exit(json_encode(['success' => false, 'message' => 'Вы не участник этой группы']));
    }
    
    // Проверяем, не единственный ли это админ
    if ($member['role'] === 'admin') {
        $stmt = $db->getConnection()->prepare("SELECT COUNT(*) as admin_count FROM chat_members WHERE chat_id = ? AND role = 'admin'");
        $stmt->execute([$chatId]);
        $adminCount = $stmt->fetch()['admin_count'];
        
        if ($adminCount <= 1) {
            exit(json_encode(['success' => false, 'message' => 'Нельзя покинуть группу - вы единственный администратор. Назначьте другого администратора или удалите группу.']));
        }
    }
    
    $db->getConnection()->beginTransaction();
    
    // Получаем имя пользователя для системного сообщения
    $stmt = $db->getConnection()->prepare("SELECT login FROM user WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch();
    
    // Удаляем участника
    $stmt = $db->getConnection()->prepare("DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $currentUserId]);
    
    // Системное сообщение
    $stmt = $db->getConnection()->prepare("
        INSERT INTO messages (user_id, chat_id, text, msg_type) 
        VALUES (?, ?, ?, 'system')
    ");
    $stmt->execute([$currentUserId, $chatId, "{$user['login']} покинул группу"]);
    
    $db->getConnection()->commit();
    
    // Очищаем активный чат
    if ($session->get('active_chat') === $chatId) {
        $session->remove('active_chat');
    }
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    error_log('Leave group error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Ошибка выхода из группы']));
}
?>