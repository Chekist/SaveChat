<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/LocalDatabase.php';
$db = LocalDatabase::getInstance()->getConnection();

$user1 = $_SESSION['user'];
$user2 = (int)($_POST['user_id'] ?? 0);

if (!$user1 || !$user2 || $user1 == $user2) {
    echo json_encode(['success' => false, 'message' => 'Неверный пользователь']);
    exit;
}

try {
    /* проверяем существование собеседника */
    $stmt = $db->prepare("SELECT id FROM user WHERE id = ?");
    if (!$stmt) throw new Exception('Prepare failed');
    $stmt->bind_param("i", $user2);
    if (!$stmt->execute()) throw new Exception('Execute failed');
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit;
    }

    /* ищем существующий чат */
    $stmt = $db->prepare(
        "SELECT chat_id FROM chat
         WHERE (user_id1 = ? AND user_id2 = ?) OR (user_id1 = ? AND user_id2 = ?)"
    );
    if (!$stmt) throw new Exception('Prepare failed');
    $stmt->bind_param("iiii", $user1, $user2, $user2, $user1);
    if (!$stmt->execute()) throw new Exception('Execute failed');
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        /* создаём новый чат c UUID */
        $chat_id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 99999999), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );

        $stmt = $db->prepare("INSERT INTO chat (chat_id, user_id1, user_id2) VALUES (?, ?, ?)");
        if (!$stmt) throw new Exception('Prepare failed');
        $stmt->bind_param("sii", $chat_id, $user1, $user2);
        if (!$stmt->execute()) throw new Exception('Chat creation failed');

        /* первое системное сообщение */
        $text = "💬 Диалог начат";
        $stmt = $db->prepare("INSERT INTO messages (user_id, chat_id, text) VALUES (?, ?, ?)");
        if (!$stmt) throw new Exception('Prepare failed');
        $stmt->bind_param("iss", $user1, $chat_id, $text);
        if (!$stmt->execute()) throw new Exception('Message creation failed');
    } else {
        $stmt->bind_result($chat_id);
        $stmt->fetch();
    }
} catch (Exception $e) {
    error_log('CreateChat error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка создания чата']);
    exit;
}

echo json_encode(['success' => true, 'chat_id' => $chat_id]);