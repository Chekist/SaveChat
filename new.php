<?php
require_once __DIR__ . '/LocalDatabase.php';
$db = LocalDatabase::getInstance()->getConnection();

const FOLDER_NAME = 'news';

// Проверка существования папки "posts" и создание её при отсутствии
if (!file_exists($folder_name)) {
    mkdir($folder_name, 0777, true);
}

// Получение и валидация данных из формы
$title = trim($_POST['title'] ?? '');
$text = trim($_POST['text'] ?? '');
$author = trim($_POST['author'] ?? '');

if (!$title || !$text || !$author) {
    http_response_code(400);
    exit('Недостаточно данных для создания новости.');
}

// Дополнительная валидация
if (strlen($title) > 255 || strlen($author) > 100) {
    http_response_code(400);
    exit('Превышена максимальная длина полей.');
}

$status = "не принята"; // Установка статуса по умолчанию

// Обработка загруженного изображения и сохранение
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    $max_size = 5 * 1024 * 1024; // 5MB
    $photo_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $unique_photo_name = uniqid() . '_' . mt_rand() . '.' . $photo_extension;
    $photo_tmp = $_FILES['photo']['tmp_name'];
    $photo_path = $folder_name . '/' . $unique_photo_name;
    
    // Защита от загрузки вредоносных файлов
    if (!in_array(strtolower($photo_extension), $allowed_types) || $_FILES['photo']['size'] > $max_size) {
        http_response_code(400);
        exit('Недопустимый тип или размер файла.');
    }
    
    if (!move_uploaded_file($photo_tmp, $photo_path)) {
        http_response_code(500);
        exit('Ошибка при загрузке файла.');
    }
}

// Получение текущей даты и времени
$datetime = gmdate("Y-m-d H:i:s");

// Использование подготовленного запроса для добавления карточки "news"
try {
    $stmt = $db->prepare("INSERT INTO news (title, text, author, nphoto, status, datetime) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $text, $author, $photo_path ?? null, $status, $datetime]);
    echo "Данные успешно добавлены в карточку 'news'.";
} catch (PDOException $e) {
    error_log('News creation error: ' . $e->getMessage());
    http_response_code(500);
    exit('Ошибка при добавлении данных.');
}
?>
<script>
setTimeout(function() {
    window.location.href = "/";
}, 1);
</script>