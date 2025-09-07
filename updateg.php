<?php
$conn = new mysqli('localhost', 'root', '', 'r9825349_mh');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$folder_name = "gallery"; // Установка имени папки

// Получение данных из формы и фильтрация
$title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
$text = filter_var($_POST['text'], FILTER_SANITIZE_STRING);
$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

// Защита от SQL инъекций
$title = $conn->real_escape_string($title);
$text = $conn->real_escape_string($text);
$id = $conn->real_escape_string($id);

// Проверка наличия нового файла изображения
$photo_path = ""; // Пустая строка, если фотография не загружена
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
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

$stmt = $conn->prepare("UPDATE gallery SET title = ?, text = ?, photo = ? WHERE id = ?");
$stmt->bind_param("sssi", $title, $text, $photo_path, $id);

$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Данные успешно обновлены в карточке 'gallery'.";
} else {
    echo "Ошибка при обновлении данных в карточке 'gallery'.";
}

// Закрытие соединения с базой данных
$stmt->close();
$conn->close();
?>
<script>
setTimeout(function() {
    window.location.href = "/";
}, 1);
</script>