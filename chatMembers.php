<?php
/* chatMembers.php
 * Возвращает JSON-массив участников группы.
 * GET: chat=int – id чата
 * Ответ: [{id,login,photo,role}, ...]
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$chat_id = ($_GET['chat'] ?? 0);
$user_id = $_SESSION['user'] ?? 0;

if (!$chat_id || !$user_id) {
    http_response_code(400);
    exit(json_encode([]));
}

require_once __DIR__ . '/LocalDatabase.php';
try {
    $db = LocalDatabase::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode([]));
}

/* проверка доступа к чату */
$stmt = $db->prepare(
    'SELECT cm.user_id FROM chat_members cm WHERE cm.chat_id = ? AND cm.user_id = ?'
);
$stmt->execute([$chat_id, $user_id]);
if (!$stmt->fetch()) {
    exit(json_encode([]));
}

/* список участников */
$stmt = $db->prepare(
    'SELECT u.id, u.login, u.photo, cm.role FROM chat_members cm JOIN user u ON u.id = cm.user_id WHERE cm.chat_id = ? ORDER BY u.login ASC'
);
$stmt->execute([$chat_id]);

$members = [];
while ($row = $stmt->fetch()) {
    $members[] = [
        'id'    => (int)$row['id'],
        'login' => htmlspecialchars($row['login']),
        'photo' => htmlspecialchars($row['photo'] ?: 'img/default-avatar.png'),
        'role'  => htmlspecialchars($row['role'])
    ];
}

echo json_encode($members);