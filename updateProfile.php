<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Config.php';

$session = SessionManager::getInstance();
$session->requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /?page=cabinet');
    exit;
}

try {
    $userId = $session->getUserId();
    $email = Validator::validateEmail($_POST['email'] ?? '');
    $about = Validator::sanitizeString($_POST['about'] ?? '', 500);
    $newPassword = $_POST['new_password'] ?? '';
    
    $db = LocalDatabase::getInstance();
    $photoPath = null;
    
    // Обработка загруженного фото
    if (!empty($_FILES['photo']['tmp_name'])) {
        $uploadDir = __DIR__ . '/user_img/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = 'usr_' . uniqid() . '.' . pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            $photoPath = 'user_img/' . $fileName;
        }
    }
    
    // Обновляем профиль
    if ($photoPath) {
        $stmt = $db->getConnection()->prepare("UPDATE user SET email = ?, about = ?, photo = ? WHERE id = ?");
        $stmt->execute([$db->encrypt($email), $about, $photoPath, $userId]);
    } else {
        $stmt = $db->getConnection()->prepare("UPDATE user SET email = ?, about = ? WHERE id = ?");
        $stmt->execute([$db->encrypt($email), $about, $userId]);
    }
    
    // Обновляем пароль если указан
    if (!empty($newPassword)) {
        $salt = Config::getPasswordSalt();
        $hash = password_hash($newPassword . $salt, PASSWORD_ARGON2ID);
        
        $stmt = $db->getConnection()->prepare("UPDATE user SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
    }
    
    header('Location: /?page=cabinet&updated=1');
    exit;
    
} catch (Exception $e) {
    error_log('Profile update error: ' . $e->getMessage());
    header('Location: /?page=cabinet&error=update');
    exit;
}
?>