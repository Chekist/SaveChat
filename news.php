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
    $text = Validator::sanitizeString($_POST['text'] ?? '', 2000);
    $author = Validator::sanitizeString($_POST['author'] ?? '', 50);
    
    if (empty($title) || empty($text)) {
        throw new InvalidArgumentException('Заголовок и текст обязательны');
    }
    
    $db = LocalDatabase::getInstance();
    $photoPath = null;
    
    if (!empty($_FILES['photo']['tmp_name'])) {
        $uploadDir = __DIR__ . '/news/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['photo']['name']);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            $photoPath = 'news/' . $fileName;
        }
    }
    
    $stmt = $db->getConnection()->prepare("
        INSERT INTO news (title, text, author, nphoto, status) 
        VALUES (?, ?, ?, ?, 'не принята')
    ");
    $stmt->execute([$title, $text, $author, $photoPath]);
    
    header('Location: /?page=cabinet');
    exit;
    
} catch (Exception $e) {
    error_log('News creation error: ' . $e->getMessage());
    header('Location: /?page=cabinet&error=news');
    exit;
}
?>