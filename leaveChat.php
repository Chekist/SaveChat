<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

try {
    $chatId = $session->get('active_chat');
    $session->remove('active_chat');
    if ($chatId) {
        $session->remove('chat_key_' . $chatId);
    }
    
    exit(json_encode(['ok' => true]));
    
} catch (Exception $e) {
    error_log('Leave chat error: ' . $e->getMessage());
    exit(json_encode(['ok' => false, 'error' => 'Failed to leave chat']));
}
?>