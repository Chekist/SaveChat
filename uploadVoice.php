<?php
/* uploadVoice.php
 * Принимает:
 *   FILES voice – blob *.ogg
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

if (empty($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode(['ok'=>false,'error'=>'no file']));
}

$tmp = $_FILES['voice']['tmp_name'];
$ext = strtolower(pathinfo($_FILES['voice']['name'],PATHINFO_EXTENSION));
if (!in_array($ext,['ogg','mp3','wav'])) {
    exit(json_encode(['ok'=>false,'error'=>'format']));
}

$dir = 'uploads/voice';
if (!is_dir($dir)) mkdir($dir,0777,true);
$path = $dir.'/'.uniqid('voice_',true).'.'.$ext;
move_uploaded_file($tmp,$path);

require_once __DIR__.'/cipher-universal.php';
require_once __DIR__.'/sendMessage.php'; // для функции saveBlobChunks

$conn = new mysqli('localhost','root','','r9825349_mh');
$stmt = $conn->prepare(
    'INSERT INTO messages (user_id,chat_id,msg_type) VALUES (?,"voice")'
);
$stmt->bind_param('ii',$user_id,$chat_id);
$stmt->execute();
$msg_id = $conn->insert_id;

saveBlobChunks($conn,$msg_id,'voice_chunks',$path);

$stmt->close();
$conn->close();

echo json_encode(['ok'=>true,'message_id'=>$msg_id,'file'=>$path]);