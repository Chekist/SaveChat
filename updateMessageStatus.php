<?php
session_start();
$chat_id = (int)($_SESSION['chat'] ?? 0);
if (!$chat_id) {
    http_response_code(400);
    exit('Invalid chat ID');
}
// Подключение к базе данных (замените на ваши реальные данные подключения)
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "r9825349_mh";

// Создание подключения
$conn = new mysqli($servername, $username, $password, $dbname);

// Проверка подключения
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Обновление статуса всех сообщений для определенного пользователя в чате
$sql = "UPDATE messages SET is_read = 1 WHERE chat_id = ? AND user_id = ?";
$stmt_messages = $conn->prepare($sql);
$stmt_messages->bind_param("ii", $chat_id, $user_id);

// Замените user_id на конкретное поле, которое идентифицирует пользователя, для которого вы хотите обновить статус сообщений

if ($stmt_messages->execute()) {
    echo "Статус всех сообщений для определенного пользователя успешно обновлен.";
} else {
    echo "Ошибка при обновлении статуса всех сообщений: " . $stmt_messages->error;
}

// Закрытие подключения
$stmt_messages->close();
$conn->close();
?>
