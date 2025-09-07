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
    $type = $_POST['type'] ?? '';
    $chatId = $_POST['chat_id'] ?? '';
    $userId = $session->getUserId();
    
    if (empty($type) || empty($chatId)) {
        exit(json_encode(['success' => false]));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Определяем текст уведомления
    $messageText = '';
    switch ($type) {
        case 'audio':
            $messageText = '📞 Входящий аудиозвонок';
            break;
        case 'video':
            $messageText = '📹 Входящий видеозвонок';
            break;
        case 'end':
            $messageText = '📞 Звонок завершен';
            break;
        default:
            exit(json_encode(['success' => false]));
    }
    
    // Сохраняем системное сообщение о звонке
    $stmt = $db->getConnection()->prepare("
        INSERT INTO messages (user_id, chat_id, text, msg_type) 
        VALUES (?, ?, ?, 'call')
    ");
    $stmt->execute([$userId, $chatId, $messageText]);
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    error_log('Call notification error: ' . $e->getMessage());
    exit(json_encode(['success' => false]));
}
?>