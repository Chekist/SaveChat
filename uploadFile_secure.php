<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/SecureFileUpload.php';
require_once __DIR__ . '/Validator.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
}

$userId = $session->getUserId();
$chatId = Validator::validateInteger($session->get('chat', 0), 1);

if (!$chatId) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'No active chat']));
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'No file uploaded']));
}

try {
    $db = LocalDatabase::getInstance();
    
    // Проверка доступа к чату
    $stmt = $db->getConnection()->prepare(
        "SELECT type, user_id1, user_id2 FROM chat WHERE chat_id = ? LIMIT 1"
    );
    $stmt->execute([$chatId]);
    $chat = $stmt->fetch();
    
    if (!$chat) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'Chat not found']));
    }
    
    $hasAccess = false;
    if ($chat['type'] === 'dialog') {
        $hasAccess = ($userId === (int)$chat['user_id1'] || $userId === (int)$chat['user_id2']);
    } elseif ($chat['type'] === 'group') {
        // Для групп проверяем членство
        $memberStmt = $db->getConnection()->prepare(
            "SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ? LIMIT 1"
        );
        $memberStmt->execute([$chatId, $userId]);
        $hasAccess = (bool)$memberStmt->fetch();
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'error' => 'Access denied']));
    }
    
    // Безопасная загрузка файла
    $uploader = new SecureFileUpload(__DIR__ . '/uploads');
    $filePath = $uploader->uploadFile($_FILES['file'], 'files');
    
    if (!$filePath) {
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'File upload failed']));
    }
    
    // Сохранение в базу данных
    $stmt = $db->getConnection()->prepare(
        "INSERT INTO messages (user_id, chat_id, photo, msg_type) VALUES (?, ?, ?, 'file')"
    );
    
    if (!$stmt->execute([$userId, $chatId, $filePath])) {
        // Удаляем файл если не удалось сохранить в БД
        $uploader->deleteFile($filePath);
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'Database save failed']));
    }
    
    $messageId = $db->getConnection()->lastInsertId();
    $fileName = basename($_FILES['file']['name']);
    
    exit(json_encode([
        'ok' => true,
        'message_id' => (int)$messageId,
        'file' => $filePath,
        'name' => Validator::sanitizeString($fileName, 255)
    ]));
    
} catch (Exception $e) {
    error_log('File upload error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Server error']));
}
?>