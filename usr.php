<?php
declare(strict_types=1);
session_start();

$user_id = (int)($_POST['id'] ?? 0);
if (!$user_id || $user_id !== ($_SESSION['user'] ?? 0)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/LocalDatabase.php';
$db = LocalDatabase::getInstance()->getConnection();

$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$about = trim(filter_var($_POST['about'] ?? '', FILTER_SANITIZE_STRING));
if (!$email) exit('Неверный e-mail');

require_once __DIR__ . '/FileUploadHandler.php';
$uploader = new FileUploadHandler();

$photo_path = '';
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    try {
        $photo_path = $uploader->upload($_FILES['photo'], 'image', 'user_img');
    } catch (RuntimeException $e) {
        exit('Ошибка загрузки фото: ' . $e->getMessage());
    }
}

$conn->begin_transaction();
if ($photo_path) {
    $stmt = $conn->prepare("UPDATE user SET email = ?, about = ?, photo = ? WHERE id = ?");
    $stmt->bind_param("sssi", $email, $about, $photo_path, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE user SET email = ?, about = ? WHERE id = ?");
    $stmt->bind_param("ssi", $email, $about, $user_id);
}
$stmt->execute();
$updated = $stmt->affected_rows;
$conn->commit();
$stmt->close();
$conn->close();

echo $updated > 0 ? 'Профиль обновлён' : 'Нет изменений';
?>