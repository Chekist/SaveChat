<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/SecureFileUpload.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
}

try {
    $messageId = Validator::validateInteger($_POST['message_id'] ?? 0, 1);
    $userId = $session->getUserId();
    
    if (!$messageId) {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Invalid message ID']));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Проверяем, что сообщение принадлежит пользователю
    $stmt = $db->getConnection()->prepare(
        "SELECT photo, msg_type FROM messages WHERE id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->execute([$messageId, $userId]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'Message not found or access denied']));
    }
    
    // Удаляем связанные файлы
    if (!empty($message['photo'])) {
        $uploader = new SecureFileUpload(__DIR__ . '/uploads');
        $uploader->deleteFile($message['photo']);
    }
    
    // Удаляем связанные записи из других таблиц (если есть)
    $relatedTables = ['voice_chunks', 'video_chunks'];
    foreach ($relatedTables as $table) {
        $deleteStmt = $db->getConnection()->prepare(
            "DELETE FROM $table WHERE message_id = ?"
        );
        $deleteStmt->execute([$messageId]);
    }
    
    // Удаляем само сообщение
    $deleteStmt = $db->getConnection()->prepare(
        "DELETE FROM messages WHERE id = ? AND user_id = ?"
    );
    
    if (!$deleteStmt->execute([$messageId, $userId])) {
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'Failed to delete message']));
    }
    
    if ($deleteStmt->rowCount() === 0) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'error' => 'Message not found']));
    }
    
    exit(json_encode(['ok' => true]));
    
} catch (Exception $e) {
    error_log('Delete message error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Server error']));
}
?>