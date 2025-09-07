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
    $text = Validator::sanitizeString($_POST['text'] ?? '', 1000);
    $author = Validator::sanitizeString($_POST['author'] ?? '', 50);
    
    if (empty($text)) {
        throw new InvalidArgumentException('Текст поста обязателен');
    }
    
    $db = LocalDatabase::getInstance();
    $photoPath = null;
    
    // Обработка загруженного фото
    if (!empty($_FILES['photo']['tmp_name'])) {
        $uploadDir = __DIR__ . '/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            $photoPath = 'posts/' . $fileName;
        }
    }
    
    $stmt = $db->getConnection()->prepare("
        INSERT INTO posts (title, text, author, pphoto, status) 
        VALUES (?, ?, ?, ?, 'не принята')
    ");
    $stmt->execute(['Пост', $text, $author, $photoPath]);
    
    header('Location: /?page=cabinet');
    exit;
    
} catch (Exception $e) {
    error_log('Post creation error: ' . $e->getMessage());
    header('Location: /?page=cabinet&error=post');
    exit;
}
?>