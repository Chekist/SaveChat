<?php
/* uploadFile.php
 * FILES file – любой тип
 * Возвращает: {ok:true, message_id:int, file:string, name:string} | {ok:false,error:string}
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user'] ?? 0;
$chat_id = $_SESSION['chat'] ?? 0;

if (!$user_id || !$chat_id) {
    http_response_code(400);
    exit(json_encode(['ok'=>false,'error'=>'auth']));
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode(['ok'=>false,'error'=>'no file']));
}

$tmp  = $_FILES['file']['tmp_name'];
$name = $_FILES['file']['name'];

$dir = 'uploads/files';
if (!is_dir($dir)) mkdir($dir,0777,true);
$path = $dir.'/'.uniqid('file_',true).'_'.basename($name);
move_uploaded_file($tmp,$path);

$conn = new mysqli('localhost','root','','r9825349_mh');
$stmt = $conn->prepare(
    'INSERT INTO messages (user_id,chat_id,photo,msg_type) VALUES (?,"file")'
);
$stmt->bind_param('iis',$user_id,$chat_id,$path);
$stmt->execute();
$msg_id = $conn->insert_id;

$stmt->close();
$conn->close();

echo json_encode(['ok'=>true,'message_id'=>$msg_id,'file'=>$path,'name'=>$name]);