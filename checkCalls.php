<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

try {
    $chatId = $session->get('active_chat');
    if (!$chatId) {
        exit(json_encode(['hasCall' => false]));
    }
    
    $db = LocalDatabase::getInstance();
    $userId = $session->getUserId();
    
    // Проверяем входящие звонки за последние 30 секунд
    $stmt = $db->getConnection()->prepare("
        SELECT text, user_id FROM messages 
        WHERE chat_id = ? AND msg_type = 'call' 
        AND user_id != ? 
        AND timestamp > datetime('now', '-30 seconds')
        AND (text LIKE '%Входящий%')
        ORDER BY timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute([$chatId, $userId]);
    $call = $stmt->fetch();
    
    if ($call) {
        $isVideo = strpos($call['text'], 'видеозвонок') !== false;
        exit(json_encode([
            'hasCall' => true,
            'type' => $isVideo ? 'video' : 'audio',
            'callerId' => $call['user_id']
        ]));
    }
    
    exit(json_encode(['hasCall' => false]));
    
} catch (Exception $e) {
    error_log('Check calls error: ' . $e->getMessage());
    exit(json_encode(['hasCall' => false]));
}
?>