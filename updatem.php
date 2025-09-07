<?php
// Подключение к базе данных
// amazonq-ignore-next-line
$conn = new mysqli('localhost', 'root', '', 'r9825349_mh');
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$folder_name = "makhnovtsy"; // Установка имени папки

// Проверка существования папки "makhnovtsy" и создание её при отсутствии
if (!file_exists($folder_name)) {
  mkdir($folder_name, 0777, true);
}

// Получение данных из формы и фильтрация
$title = htmlspecialchars(filter_var($_POST['name'], FILTER_SANITIZE_STRING));
$text = htmlspecialchars(filter_var($_POST['text'], FILTER_SANITIZE_STRING));
$id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT); // Получение и фильтрация ID записи

// Проверка наличия нового файла изображения
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
  $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
  $max_size = 5 * 1024 * 1024; // 5MB
  $photo_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
  $unique_photo_name = uniqid() . '_' . mt_rand() . '.' . $photo_extension;
  $photo_tmp = $_FILES['photo']['tmp_name'];
  $photo_path = $folder_name . '/' . $unique_photo_name;
   
  // Защита от загрузки вредоносных файлов
  if (in_array(strtolower($photo_extension), $allowed_types) && $_FILES['photo']['size'] <= $max_size) {
    if (!move_uploaded_file($photo_tmp, $photo_path)) {
      http_response_code(500);
      exit('Ошибка при загрузке файла.');
    }
  } else {
    http_response_code(400);
    exit('Недопустимый тип или размер файла.');
  }
} else {
  $photo_path = ""; // Пустая строка, если фотография не загружена
}

// Использование подготовленного запроса для обновления поста по ID
// amazonq-ignore-next-line
$stmt = $conn->prepare("UPDATE makhnovtsy SET name = ?, text = ?, mphoto = ? WHERE id = ?");
$stmt->bind_param("sssi", $title, $text, $photo_path, $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo "Пост успешно обновлен.";
} else {
  echo "Ошибка при обновлении поста.";
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