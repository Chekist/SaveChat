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
    $groupName = Validator::sanitizeString($_POST['groupName'] ?? '', 100);
    $currentUserId = $session->getUserId();
    
    $db = LocalDatabase::getInstance();
    
    // Любой может редактировать группу
    
    $avatarPath = null;
    
    // Обработка загруженного аватара
    if (!empty($_FILES['avatar']['tmp_name'])) {
        $uploadDir = __DIR__ . '/group_avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = 'group_' . uniqid() . '.' . pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
            $avatarPath = 'group_avatars/' . $fileName;
        }
    }
    
    // Получаем текущие настройки
    $stmt = $db->getConnection()->prepare("SELECT avatar FROM group_settings WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $current = $stmt->fetch();
    $currentAvatar = $current ? $current['avatar'] : null;
    
    // Обновляем настройки
    $finalAvatar = $avatarPath ?: $currentAvatar;
    $stmt = $db->getConnection()->prepare("
        INSERT OR REPLACE INTO group_settings (chat_id, name, avatar) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$chatId, $groupName, $finalAvatar]);
    
    // Добавляем системное сообщение
    $updateMsg = "Группа обновлена: " . $groupName;
    $stmt = $db->getConnection()->prepare("
        INSERT INTO messages (user_id, chat_id, text, msg_type) 
        VALUES (?, ?, ?, 'system')
    ");
    $stmt->execute([$currentUserId, $chatId, $updateMsg]);
    
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    error_log('Update group error: ' . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Ошибка обновления группы']));
}
?>