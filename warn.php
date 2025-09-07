<?php
// Подключение к базе данных
$conn = new mysqli('localhost', 'root', '', 'r9825349_mh');

// Проверка соединения
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Создание таблицы, если она не существует
$sql_create_table = "CREATE TABLE IF NOT EXISTS warnings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(30) NOT NULL,
    post_id INT(6) NOT NULL,
    user_id INT(6) NOT NULL,
    warning_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_create_table);

// Проверка наличия данных в POST-запросе
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    // Подготовка данных из запроса
    $category = trim($_POST['category'] ?? '');
    $post_id = (int)($_POST['post_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $warning_text = trim($_POST['warning_text'] ?? '');

    // Проверка данных перед вставкой в таблицу
    if (empty($category) || empty($post_id) || empty($user_id) || empty($warning_text)) {
        die("One or more required fields are empty");
    }

    // Подготовка запроса на вставку предупреждения в таблицу
    $sql_insert_warning = "INSERT INTO warnings (category, post_id, user_id, warning_text) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql_insert_warning);
    $stmt->bind_param("siis", $category, $post_id, $user_id, $warning_text);

    // Выполнение подготовленного запроса
    if ($stmt->execute()) {
        echo "Warning added successfully";
    } else {
        echo "Error adding warning: " . $conn->error;
    }

    // Закрытие подготовленного запроса
    $stmt->close();

    // Проверка на уникальность пользователя
    $checkUserQuery = "SELECT * FROM user WHERE id = '$user_id'";
    $result = $conn->query($checkUserQuery);
    if ($result->num_rows > 0) {
        $existingUser = $result->fetch_assoc();

        // PHP код для отправки электронного письма пользователю
        ini_set('SMTP', 'smtp.beget.com');
        ini_set('smtp_port', 2525);
        ini_set('sendmail_from', 'nimakhno@nv-prestige.ru');

        $to = $existingUser['email'];
        $subject = "Вам пришло предупреждение в N.I.Makhno!";
        
        $message = '<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Красивое письмо</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <p>Добрый день ' . $existingUser['login'] . '!</p>
        <p>Этим письмом мы хотим уведомить вас о том, что вам пришло предупреждение!</p>
    </div>
</body>
</html>';
        $headers = "From: nimakhno@nv-prestige.ru\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";

        // Отправка письма
        if (mail($to, $subject, $message, $headers)) {
            echo json_encode(array("success" => true, "message" => $existingUser['login'] . "!"));
        } else {
            echo json_encode(array("success" => false, "message" => "Ошибка при отправке письма"));
        }
    }
}

// Закрытие соединения с базой данных
$conn->close();
?>
<script>
  setTimeout(function() {
    window.location.href = "/";
  }, 1);
</script>
