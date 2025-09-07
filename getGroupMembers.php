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
    $currentUserId = $session->getUserId();
    
    // Проверяем, что пользователь участник группы
    $stmt = $db->getConnection()->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $currentUserId]);
    if (!$stmt->fetch()) {
        exit(json_encode([]));
    }
    
    // Получаем список участников группы
    $stmt = $db->getConnection()->prepare("
        SELECT u.id, u.login, u.photo, u.about, u.last_activity, cm.role
        FROM chat_members cm
        JOIN user u ON cm.user_id = u.id
        WHERE cm.chat_id = ?
        ORDER BY 
            CASE cm.role 
                WHEN 'admin' THEN 1 
                WHEN 'moderator' THEN 2 
                ELSE 3 
            END,
            u.login
    ");
    $stmt->execute([$chatId]);
    $members = $stmt->fetchAll();
    
    $result = [];
    foreach ($members as $member) {
        $isOnline = false;
        if ($member['last_activity']) {
            $isOnline = (time() - strtotime($member['last_activity'])) < 300;
        }
        
        $roleIcon = '';
        switch ($member['role']) {
            case 'admin':
                $roleIcon = '👑';
                break;
            case 'moderator':
                $roleIcon = '⚙️';
                break;
            default:
                $roleIcon = '👤';
        }
        
        $result[] = [
            'id' => $member['id'],
            'login' => $member['login'],
            'photo' => $member['photo'],
            'about' => $member['about'],
            'online' => $isOnline,
            'role' => $member['role'],
            'role_icon' => $roleIcon
        ];
    }
    
    exit(json_encode($result));
    
} catch (Exception $e) {
    error_log('Get group members error: ' . $e->getMessage());
    exit(json_encode([]));
}
?>