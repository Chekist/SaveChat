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
    $title = Validator::sanitizeString($_POST['title'] ?? '', 200);
    $text = Validator::sanitizeString($_POST['text'] ?? '', 1000);
    $author = Validator::sanitizeString($_POST['author'] ?? '', 50);
    
    if (empty($title) || empty($_FILES['photo']['tmp_name'])) {
        throw new InvalidArgumentException('Название и фото обязательны');
    }
    
    $userId = $session->getUserId();
    $uploadDir = __DIR__ . '/gallery/user_' . $userId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
        // Сохраняем метаданные в базу
        $db = LocalDatabase::getInstance();
        $stmt = $db->getConnection()->prepare("
            INSERT INTO gallery_photos (user_id, filename, title, description) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $fileName, $title, $text]);
        
        header('Location: /?page=cabinet');
        exit;
    } else {
        throw new Exception('Ошибка загрузки файла');
    }
    
} catch (Exception $e) {
    error_log('Gallery upload error: ' . $e->getMessage());
    header('Location: /?page=cabinet&error=gallery');
    exit;
}
?>