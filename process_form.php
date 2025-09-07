<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';

$session = SessionManager::getInstance();
$session->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /?page=cabinet');
    exit;
}

try {
    $postId = Validator::validateInteger($_POST['post_id'] ?? 0, 1);
    $tableName = Validator::sanitizeString($_POST['table_name'] ?? '', 20);
    $status = Validator::sanitizeString($_POST['status'] ?? '', 20);
    
    if (!in_array($tableName, ['posts', 'news'])) {
        throw new InvalidArgumentException('Invalid table name');
    }
    
    if (!in_array($status, ['Принят', 'Отклонен', 'Удалить'])) {
        throw new InvalidArgumentException('Invalid status');
    }
    
    $db = LocalDatabase::getInstance();
    
    if ($status === 'Удалить') {
        $stmt = $db->getConnection()->prepare("DELETE FROM {$tableName} WHERE id = ?");
        $stmt->execute([$postId]);
    } else {
        $stmt = $db->getConnection()->prepare("UPDATE {$tableName} SET status = ? WHERE id = ?");
        $stmt->execute([$status, $postId]);
    }
    
    header('Location: /?page=cabinet');
    exit;
    
} catch (Exception $e) {
    error_log('Process form error: ' . $e->getMessage());
    header('Location: /?page=cabinet&error=1');
    exit;
}
?>