<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['ok' => false, 'error' => 'Method not allowed']));
}

try {
    $chatId = $_POST['chat'] ?? '';
    $passPhrase = $_POST['passPhrase'] ?? '';
    
    if (empty($chatId)) {
        exit(json_encode(['ok' => false, 'error' => 'Chat ID required']));
    }
    
    // Сохраняем текущий ключ
    $session->set('current_chat_key', $passPhrase ?: '');
    
    exit(json_encode(['ok' => true, 'message' => 'Ключ сохранен']));
    
} catch (Exception $e) {
    error_log('Save key error: ' . $e->getMessage());
    exit(json_encode(['ok' => false, 'error' => 'Failed to save key']));
}
?>