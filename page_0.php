<?php
declare(strict_types=1);

session_start();

$chat = $_POST['chat'] ?? null;
$pg   = $_POST['pg'] ?? '';

if ($pg === 'logout') {
    $_SESSION['page'] = 'home';
    $_SESSION['user'] = 0;
    header("Location: /");
    exit;
} else {
    if ($chat !== null && $chat !== '') {
        $_SESSION['chat'] = $chat;
    }
    if ($pg !== '') {
        $_SESSION['page'] = $pg;
    }
    header("Location: /");
    exit;
}
?>