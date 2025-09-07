<?php
$conn = new mysqli('localhost', 'root', '', 'r9825349_mh');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$folder_name = "news"; // Установка имени папки

// Получение данных из формы и фильтрация
// amazonq-ignore-next-line
// amazonq-ignore-next-line
$title = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
$text = filter_var($_POST['text'], FILTER_SANITIZE_STRING);

// Защита от SQL инъекций
$title = $conn->real_escape_string($title);
$text = $conn->real_escape_string($text);

$photo_path = ""; // Пустая строка, если фотография не загружена
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    $max_size = 5 * 1024 * 1024; // 5MB
    $photo_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $unique_photo_name = uniqid() . '_' . mt_rand() . '.' . $photo_extension;
    $photo_tmp = $_FILES['photo']['tmp_name'];
    $photo_path = $folder_name . '/' . $unique_photo_name;

    // Защита от загрузки вредоносных файлов
    if (in_array(strtolower($photo_extension), $allowed_types) && $_FILES['photo']['size'] <= $max_size) {
        move_uploaded_file($photo_tmp, $photo_path);
    } else {
        echo "Недопустимый тип или размер файла.";
    }
}

// Получение ID записи для обновления
$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

if (empty($photo_path)) {
    $stmt_update = $conn->prepare("UPDATE news SET title = ?, text = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $title, $text, $id);
} else {
    $stmt_update = $conn->prepare("UPDATE news SET title = ?, text = ?, photo = ? WHERE id = ?");
    $stmt_update->bind_param("sssi", $title, $text, $photo_path, $id);
}

$stmt_update->execute();

if ($stmt_update->affected_rows > 0) {
    echo "Новость успешно обновлена.";
} else {
    echo "Ошибка при обновлении новости.";
}

// Закрытие соединения с базой данных
$stmt_update->close();
$conn->close();
?>
<script>
setTimeout(function() {
    window.location.href = "/";
}, 1);
</script>