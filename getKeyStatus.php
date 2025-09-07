<?php
require_once __DIR__ . '/SessionManager.php';

$session = SessionManager::getInstance();
header('Content-Type: application/json');

$currentKey = $session->get('current_chat_key');
exit(json_encode(['hasKey' => !empty($currentKey)]));
?>