<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

try {
    $db = LocalDatabase::getInstance();
    $currentUserId = $session->getUserId();
    
    if (!$currentUserId) {
        exit(json_encode(['error' => 'User not logged in']));
    }
    
    $connection = $db->getConnection();
    if (!$connection) {
        exit(json_encode(['error' => 'Database connection failed']));
    }
    
    $result = [];
    
    // 1. Получаем групповые чаты где пользователь участник
    $stmt = $connection->prepare("
        SELECT cm.chat_id, gs.name, gs.avatar,
               (SELECT text FROM messages WHERE chat_id = cm.chat_id ORDER BY timestamp DESC LIMIT 1) as last_message,
               (SELECT timestamp FROM messages WHERE chat_id = cm.chat_id ORDER BY timestamp DESC LIMIT 1) as last_time
        FROM chat_members cm
        LEFT JOIN group_settings gs ON cm.chat_id = gs.chat_id
        WHERE cm.user_id = ? AND cm.chat_id LIKE 'group_%'
        ORDER BY last_time DESC
    ");
    $stmt->execute([$currentUserId]);
    $groupChats = $stmt->fetchAll();
    
    foreach ($groupChats as $chat) {
        $result[] = [
            'chat_id' => $chat['chat_id'],
            'name' => '👥 ' . ($chat['name'] ?: 'Группа'),
            'avatar' => $chat['avatar'] ?: 'img/default-avatar.svg',
            'preview' => $chat['last_message'] ? substr($chat['last_message'], 0, 50) . '...' : 'Нет сообщений',
            'time' => $chat['last_time'] ? date('H:i', strtotime($chat['last_time'])) : '',
            'online' => false,
            'unread' => 0,
            'type' => 'group'
        ];
    }
    
    // 2. Получаем диалоги
    $stmt = $connection->prepare("
        SELECT c.chat_id, c.user_id1, c.user_id2,
               (SELECT text FROM messages WHERE chat_id = c.chat_id ORDER BY timestamp DESC LIMIT 1) as last_message,
               (SELECT timestamp FROM messages WHERE chat_id = c.chat_id ORDER BY timestamp DESC LIMIT 1) as last_time
        FROM chat c
        WHERE c.type = 'dialog' AND (c.user_id1 = ? OR c.user_id2 = ?)
        ORDER BY last_time DESC
    ");
    $stmt->execute([$currentUserId, $currentUserId]);
    $dialogs = $stmt->fetchAll();
    
    foreach ($dialogs as $dialog) {
        $partnerId = ($dialog['user_id1'] == $currentUserId) ? $dialog['user_id2'] : $dialog['user_id1'];
        
        // Получаем информацию о собеседнике
        $partnerStmt = $connection->prepare("SELECT login, photo FROM user WHERE id = ?");
        $partnerStmt->execute([$partnerId]);
        $partner = $partnerStmt->fetch();
        
        if ($partner) {
            $isOnline = false;
            
            $result[] = [
                'chat_id' => $dialog['chat_id'],
                'name' => $partner['login'],
                'avatar' => $partner['photo'] ?: 'img/default-avatar.svg',
                'preview' => $dialog['last_message'] ? substr($dialog['last_message'], 0, 50) . '...' : 'Нет сообщений',
                'time' => $dialog['last_time'] ? date('H:i', strtotime($dialog['last_time'])) : '',
                'online' => $isOnline,
                'unread' => 0,
                'type' => 'dialog'
            ];
        }
    }
    
    exit(json_encode($result));
    
} catch (Exception $e) {
    error_log('Get chats error: ' . $e->getMessage());
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>