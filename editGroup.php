<?php
/* editGroup.php
 * POST:
 *   chat_id int
 *   title   string – новое название (опц.)
 *   avatar  file   – новый аватар   (опц.)
 * Возвращает {success:true} | {success:false,message:string}
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user'] ?? 0;
$chat_id = (int)($_POST['chat_id'] ?? 0);

if (!$user_id || !$chat_id) {
    echo json_encode(['success'=>false,'message'=>'no data']); exit;
}

$title = trim($_POST['title'] ?? '');
$has_file = isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK;

if ($title === '' && !$has_file) {
    echo json_encode(['success'=>false,'message'=>'nothing to change']); exit;
}

$conn = new mysqli('localhost','root','','r9825349_mh');
if ($conn->connect_error) { echo json_encode(['success'=>false,'message'=>'db']); exit; }

/* проверяем, что пользователь – админ этой группы */
$stmt = $conn->prepare(
    'SELECT 1 FROM chat_members
     WHERE chat_id = ? AND user_id = ? AND role = "admin"'
);
$stmt->bind_param('ii', $chat_id, $user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success'=>false,'message'=>'forbidden']); exit;
}
$stmt->close();

/* обрабатываем новый аватар */
$avatar_sql = '';
if ($has_file) {
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext,['jpg','jpeg','png','gif'])) {
        echo json_encode(['success'=>false,'message'=>'bad format']); exit;
    }
    $path = 'group_avatars/' . uniqid('grp_', true) . '.' . $ext;
    if (!is_dir('group_avatars')) mkdir('group_avatars', 0777, true);
    move_uploaded_file($_FILES['avatar']['tmp_name'], $path);
    $avatar_sql = ', avatar = ?';
}

/* обновляем базу */
$sql = "UPDATE chat SET title = ? {$avatar_sql} WHERE chat_id = ?";
$stmt = $conn->prepare($sql);
if ($has_file) {
    $stmt->bind_param('ssi', $title, $path, $chat_id);
} else {
    $stmt->bind_param('si', $title, $chat_id);
}
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success'=>(bool)$ok]);