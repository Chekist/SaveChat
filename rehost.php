<?php
session_start();

// Валидация и очистка данных от пользователя
$usr = filter_input(INPUT_POST, 'usr', FILTER_SANITIZE_STRING);

if (!empty($usr)) {
    // Экранирование вывода
    $usr = htmlspecialchars($usr, ENT_QUOTES, 'UTF-8');

    $_SESSION['page'] = 'cabinet';
    echo '<script>
    setTimeout(function() {
        window.location.href = "/?usrid=' . $usr . '";
    }, 1);
    </script>';
} else {
    echo 'Pidr';
    echo '<script> 
    setTimeout(function() {
        window.location.href = "/";
    }, 1);
   </script>';
}

echo htmlspecialchars($_SESSION['page'], ENT_QUOTES, 'UTF-8');
?>