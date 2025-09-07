<?php
/* addMember.php
 * POST:
 *   chat_id int
 *   user_id int – кого добавить
 * Возвращает {success:true} | {success:false,message:string}
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user'] ?? 0;
$chat_id = (int)($_POST['chat_id'] ?? 0);
$add_id  = (int)($_POST['user_id'] ?? 0);

if (!$user_id || !$chat_id || !$add_id) {
    echo json_encode(['success'=>false,'message'=>'no data']); exit;
}

try {
    require_once __DIR__ . '/LocalDatabase.php';
    $db = LocalDatabase::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Database error']));
}

/* проверяем, что запрашивающий – админ группы */
$stmt = $db->prepare('SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ? AND role = "admin"');
$stmt->execute([$chat_id, $user_id]);
if (!$stmt->fetch()) {
    exit(json_encode(['success' => false, 'message' => 'forbidden']));
}

/* проверяем существование добавляемого */
$stmt = $db->prepare('SELECT id FROM user WHERE id = ?');
$stmt->execute([$add_id]);
if (!$stmt->fetch()) {
    exit(json_encode(['success' => false, 'message' => 'user not found']));
}

/* проверяем, не добавлен ли уже */
$stmt = $db->prepare('SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?');
$stmt->execute([$chat_id, $add_id]);
if ($stmt->fetch()) {
    exit(json_encode(['success' => false, 'message' => 'already member']));
}

/* добавляем */
$stmt = $db->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?, ?, ?)');
$success = $stmt->execute([$chat_id, $add_id, 'member']);

echo json_encode(['success' => $success]);