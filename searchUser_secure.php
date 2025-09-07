<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/SecurityHelper.php';

$session = SessionManager::getInstance();
$session->requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

try {
    $search = Validator::sanitizeString($_POST['search'] ?? '', 100);
    
    if (empty($search)) {
        exit(json_encode([]));
    }
    
    // Проверка на подозрительную активность
    if (SecurityHelper::detectSuspiciousActivity($search)) {
        SecurityHelper::logSuspiciousActivity('search_xss_attempt', $search);
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid search query']));
    }
    
    $db = LocalDatabase::getInstance();
    
    // Безопасный поиск с лимитом
    $stmt = $db->getConnection()->prepare(
        "SELECT id, login, photo FROM user 
         WHERE login LIKE ? AND id != ? 
         ORDER BY login ASC 
         LIMIT 10"
    );
    
    $searchPattern = '%' . $search . '%';
    $currentUserId = $session->getUserId();
    
    $stmt->execute([$searchPattern, $currentUserId]);
    $users = $stmt->fetchAll();
    
    // Дополнительная санитизация результатов
    $safeUsers = [];
    foreach ($users as $user) {
        $safeUsers[] = [
            'id' => (int)$user['id'],
            'login' => SecurityHelper::escapeHtml($user['login']),
            'photo' => SecurityHelper::escapeHtml($user['photo'] ?: 'img/default-avatar.png')
        ];
    }
    
    exit(json_encode($safeUsers));
    
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Search failed']));
}
?>