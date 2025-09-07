<?php
/* uploadVideo.php
 * FILES video – *.mp4
 * Возвращает: {ok:true, message_id:int, file:string} | {ok:false,error:string}
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user'] ?? 0;
$chat_id = $_SESSION['chat'] ?? 0;

if (!$user_id || !$chat_id) {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'error'=>'auth']));
}

if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode(['ok'=>false,'error'=>'no file']));
}

// Быстрые проверки расширения и размера
$ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
$allowedExt = ['mp4','mov','3gp'];
$maxSize = 50 * 1024 * 1024; // 50 MB

if (!in_array($ext, $allowedExt)) {
    exit(json_encode(['ok'=>false,'error'=>'format']));
}

if ($_FILES['video']['size'] > $maxSize) {
    exit(json_encode(['ok'=>false,'error'=>'size']));
}

// Проверка MIME-типа
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['video']['tmp_name']);
finfo_close($finfo);
$allowedMime = ['video/mp4','video/quicktime','video/3gpp'];
if (!in_array($mime, $allowedMime)) {
    exit(json_encode(['ok'=>false,'error'=>'mime']));
}

require_once __DIR__ . '/FileUploadHandler.php';
require_once __DIR__ . '/sendMessage.php'; // для saveBlobChunks

$uploader = new FileUploadHandler();

$path = '';
try {
    // Загружаем файл прежде чем писать запись в БД
    $path = $uploader->upload($_FILES['video'], 'video', 'uploads/video');

    // Подключаемся к БД через mysqli — saveBlobChunks в проекте ожидает mysqli
    $conn = new mysqli('localhost','root','','r9825349_mh');
    if ($conn->connect_error) {
        throw new RuntimeException('db_connect');
    }

    $conn->begin_transaction();

    $stmt = $conn->prepare('INSERT INTO messages (user_id,chat_id,msg_type) VALUES (?,?, "video")');
    if (!$stmt) throw new RuntimeException('db_prepare');

    if (!$stmt->bind_param('ii', $user_id, $chat_id)) throw new RuntimeException('db_bind');
    if (!$stmt->execute()) throw new RuntimeException('db_execute');

    $msg_id = $conn->insert_id;

    // Сохраняем блобы (реализация saveBlobChunks использует mysqli)
    if (!function_exists('saveBlobChunks')) {
        throw new RuntimeException('missing_saveBlobChunks');
    }
    saveBlobChunks($conn, (int)$msg_id, 'video_chunks', $path);

    $conn->commit();

    $stmt->close();
    $conn->close();

    echo json_encode(['ok'=>true,'message_id'=> (int)$msg_id,'file'=>$path]);
    exit;

} catch (Exception $e) {
    // откат и очистка файла при ошибке
    if (!empty($conn) && $conn instanceof mysqli && $conn->connect_errno === 0) {
        $conn->rollback();
        $conn->close();
    }
    if ($path && file_exists($path)) {
        @unlink($path);
    }
    error_log('uploadVideo error: ' . $e->getMessage());
    exit(json_encode(['ok'=>false,'error'=>'server']));
}
?>