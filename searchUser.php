<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

try {
    $search = Validator::sanitizeString($_POST['search'] ?? '', 50);
    
    if (strlen($search) < 2) {
        exit(json_encode([]));
    }
    
    $db = LocalDatabase::getInstance();
    $currentUserId = $session->getUserId();
    
    $stmt = $db->getConnection()->prepare("
        SELECT id, login, photo 
        FROM user 
        WHERE login LIKE ? AND id != ? 
        LIMIT 10
    ");
    $searchTerm = '%' . $search . '%';
    $stmt->execute([$searchTerm, $currentUserId]);
    $users = $stmt->fetchAll();
    
    // Безопасная обработка результатов
    $result = [];
    foreach ($users as $user) {
        $result[] = [
            'id' => (int)$user['id'],
            'login' => $user['login'],
            'photo' => $user['photo'] ?: 'img/default-avatar.png'
        ];
    }
    
    exit(json_encode($result));
    
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Search failed']));
}
?>