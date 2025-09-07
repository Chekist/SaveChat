<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/SecurityHelper.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
}

try {
    $chatId = $session->get('active_chat');
    if (!$chatId) {
        exit(json_encode(['ok' => false, 'error' => 'No active chat']));
    }
    
    $message = trim($_POST['message'] ?? '');
    $userId = $session->getUserId();
    
    if (empty($message) && empty($_FILES)) {
        exit(json_encode(['ok' => false, 'error' => 'Empty message']));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Для групповых чатов проверяем участие
    if (strpos($chatId, 'group_') === 0) {
        $stmt = $db->getConnection()->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chatId, $userId]);
        if (!$stmt->fetch()) {
            exit(json_encode(['ok' => false, 'error' => 'Вы не участник этой группы']));
        }
    }
    
    $photoPath = null;
    
    // Обработка загруженных файлов
    if (!empty($_FILES['photos']) && !empty($_FILES['photos']['tmp_name'][0])) {
        $uploadDir = __DIR__ . '/uploads/msg/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['photos'];
        $fileName = SecurityHelper::generateSecureFilename($file['name'][0]);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'][0], $uploadPath)) {
            $photoPath = 'uploads/msg/' . $fileName;
        }
    }
    
    // Шифруем сообщение с текущим ключом
    $encryptedText = null;
    $currentKey = $session->get('current_chat_key');
    if (!empty($message)) {
        if (!empty($currentKey)) {
            $encryptedText = $db->encrypt($message . '::KEY::' . $currentKey);
            $message = null; // Очищаем открытый текст
        }
        // Если ключа нет, сообщение остается в открытом виде (небезопасно)
    }
    
    // Сохраняем сообщение
    $stmt = $db->getConnection()->prepare("
        INSERT INTO messages (user_id, chat_id, text, text_cipher, photo, msg_type) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $msgType = $photoPath ? 'image' : 'text';
    $stmt->execute([$userId, $chatId, $message, $encryptedText, $photoPath, $msgType]);
    
    exit(json_encode(['ok' => true]));
    
} catch (Exception $e) {
    error_log('Send message error: ' . $e->getMessage());
    exit(json_encode(['ok' => false, 'error' => 'Failed to send message']));
}
?>