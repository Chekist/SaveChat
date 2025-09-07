<?php
require_once __DIR__ . '/SessionManager.php';
$session = SessionManager::getInstance();

// Защита от XSS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="img/favicon.jpg" type="image/x-icon">
  <link rel="stylesheet" href="assets/css/modern-style.css">
  <title>Современный мессенджер</title>
</head>
<body>
<?php
$page = $_GET['page'] ?? 'home';
$user = $session->getUserId();
$session->set('page', $page);

include("header_modern.php");

switch($page) {
    case 'home':
    case '':
        include("home_modern.php");
        break;
    case 'art':
        include("art.php");
        break;
    case 'news':
        include("news_secure.php");
        break;
    case 'login':
        if($session->isLoggedIn()) {
            header('Location: /?page=cabinet');
            exit;
        }
        include("login_modern.php");
        break;
    case 'register':
        if($session->isLoggedIn()) {
            header('Location: /?page=cabinet');
            exit;
        }
        include("register_modern.php");
        break;
    case 'cabinet':
        if($session->isLoggedIn()){
            include("cabinet.php");
        }else{
            header('Location: /?page=login');
            exit;
        }
        break;
    case 'msg':
        if($session->isLoggedIn()){
            include("msg_secure.php");
        }else{
            header('Location: /?page=login');
            exit;
        }
        break;
    case 'chat':
        if($session->isLoggedIn()){
            include("chat_modern.php");
        }else{
            header('Location: /?page=login');
            exit;
        }
        break;
    default:
        include("home_modern.php");
}

include("footer_modern.php");
?>
</body>
</html>