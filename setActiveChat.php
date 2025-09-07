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
    
    if (empty($chatId)) {
        exit(json_encode(['success' => false, 'message' => 'Chat ID required']));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Для групповых чатов проверяем участие
    if (strpos($chatId, 'group_') === 0) {
        $stmt = $db->getConnection()->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chatId, $currentUserId]);
        if (!$stmt->fetch()) {
            exit(json_encode(['success' => false, 'message' => 'Вы не участник этой группы']));
        }
    } else {
        // Для диалогов проверяем, что пользователь участник
        $stmt = $db->getConnection()->prepare("SELECT 1 FROM chat WHERE chat_id = ? AND (user_id1 = ? OR user_id2 = ?)");
        $stmt->execute([$chatId, $currentUserId, $currentUserId]);
        if (!$stmt->fetch()) {
            exit(json_encode(['success' => false, 'message' => 'Чат не найден']));
        }
    }
    
    // Устанавливаем активный чат
    $session->set('active_chat', $chatId);
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    error_log('Set active chat error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Ошибка установки активного чата']));
}
?>