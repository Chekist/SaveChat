<?php
/**
 * Unified, safe router page
 * -------------------------
 * POST/GET: ?pg=<target>&chat=<chat_id>
 */

session_start();

/* ---------- 1. Sanitize incoming data ---------- */
$target = isset($_POST['pg']) ? $_POST['pg'] : (isset($_GET['pg']) ? $_GET['pg'] : '');
$chat   = isset($_POST['chat']) ? $_POST['chat'] : (isset($_GET['chat']) ? $_GET['chat'] : 0);

/* ---------- 2. Handle logout ---------- */
if ($target === 'logout') {
    $_SESSION = [];                 // wipe session
    session_destroy();
    header('Location: /');
    exit;
}

/* ---------- 3. Handle chat set/reset ---------- */
if ($chat > 0) {
    $_SESSION['chat'] = $chat;
}

/* ---------- 4. Default fallback ---------- */
$allowedPages = [
    'home',
    'art',
    'news',
    'login',
    'register',
    'cabinet',
    'msg',
    'chat',
];

if (in_array($target, $allowedPages, true)) {
    $_SESSION['page'] = $target;
} else {
    $_SESSION['page'] = 'home';     // fallback to home
}

/* ---------- 5. Redirect once and stop ---------- */
header('Location: /');
exit;