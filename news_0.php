

<style>
        .tab {
        display: none;
    }
    .emp-profile{
        min-height:700px;
    }
    .card{
        margin:10px;
        padding:10px;
    }
    h1{
        text-align:center;
    }
</style>
<?php
$conn = new mysqli('localhost', 'root', '', 'r9825349_mh');

// Проверка соединения
if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
}

echo'<div class="container emp-profile">';

    echo '<h1>Новости</h1>';


echo '<div class="card-columns">';
$sql_news = "SELECT * FROM news WHERE status = 'Принят' ORDER BY datetime DESC";
$result_news = $conn->query($sql_news);

// Обработка результатов запроса для новостей
if ($result_news->num_rows > 0) {
    // Вывод карточек новостей
    while ($row_news = $result_news->fetch_assoc()) {
        $timestamp = strtotime($row_news['datetime']);
        $date_strings = date('j F Y в H.i', $timestamp);
        
        if($row_news['nphoto'] == null){
            echo '<div class="card">';
            echo '<div class="card-block">';
            echo '<a title="' . $row_news['author'] . '">';
            echo '<h4 class="card-title px-2"></h4>';
            echo '</a>';
            // amazonq-ignore-next-line
            echo '<p class="card-text px-2">' . $row_news['title'] . '</p>';
            echo '<small class="text-muted">Дата: ' . $date_strings . '</small>';
            echo '</div>';
            echo '<div class="card-footer">';
            echo '<small class="text-muted"> ' . $row_news['text'] . '</small>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="card">';
            echo '<img class="card-img-top" src="' . $row_news['nphoto'] . '" alt="' . $row_news['title'] . '">';
            echo '<div class="card-block">';
            echo '<a title="' . $row_news['author'] . '">';
            echo '<h4 class="card-title px-2"></h4>';
            echo '</a>';
            echo '<p class="card-text px-2">' . $row_news['title'] . '</p>';
             echo '<ul class="list-group list-group-flush">';
        echo '<li class="list-group-item">' . $row_news['status'] . '</li>';
        $author = $row_news['author'];
        // amazonq-ignore-next-line
        $sql = "SELECT id FROM user WHERE login = '$author'";
        $result = $conn->query($sql);

        $row = $result->fetch_assoc();
        echo '<li class="list-group-item"><form method="post" action="rehost.php"><input type="text" name="usr" value="' . $row['id'] . '" hidden><button type="submit" style="background: none; border: none; color: blue; text-decoration: underline; cursor: pointer;">' . $row_news['author'] . '</button></form></li>';
        echo '<li class="list-group-item">Дата: ' . $date_string . '</li>'; // Добавлено поле даты аналогично указанному формату
        echo '</ul>';
            echo '</div>';
            echo '<div class="card-footer">';
            echo '<small class="text-muted"> ' . $row_news['text'] . '</small>';
            echo '</div>';
            echo '</div>';
        }
    }
} else {
    echo 'Новостей не найден';
}

echo '</div>';
echo '</div>';
?>