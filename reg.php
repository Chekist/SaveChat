<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/SecurityHelper.php';

$session = SessionManager::getInstance();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Метод не разрешен']));
}

try {
    // Проверка CSRF токена: допускаем или session CSRF, или stateless XSRF (double-submit cookie)
    $formToken = $_POST['csrf_token'] ?? '';
    $headerXsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $csrfOk = SecurityHelper::validateCSRFToken($formToken) || SecurityHelper::validateXsrf($headerXsrf);
    if (!$csrfOk) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'CSRF токен недействителен']));
    }
    
    $login = Validator::sanitizeString($_POST['login'] ?? '', 50);
    $password = $_POST['password'] ?? '';
    $email = Validator::validateEmail($_POST['email'] ?? '');
    $repeat = $_POST['repeat_password'] ?? '';
    
    if (empty($login) || empty($password) || empty($email) || empty($repeat)) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Заполните все поля']));
    }
    
    if ($password !== $repeat) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Пароли не совпадают']));
    }
    
    // Проверка логина
    if (strlen($login) < 3 || strlen($login) > 50) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Логин должен быть от 3 до 50 символов']));
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Логин может содержать только буквы, цифры и _']));
    }
    
    // Проверка пароля
    if (strlen($password) < 8 || strlen($password) > 255) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Пароль должен быть от 8 до 255 символов']));
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Пароль должен содержать заглавные, строчные буквы и цифры']));
    }
    
    $db = LocalDatabase::getInstance();
    $pdo = $db->getConnection();
    
    // Проверка уникальности логина (прямая)
    $stmt = $pdo->prepare("SELECT 1 FROM user WHERE login = ? LIMIT 1");
    $stmt->execute([$login]);
    if ($stmt->fetch()) {
        http_response_code(409);
        exit(json_encode(['success' => false, 'message' => 'Логин уже занят']));
    }
    
    // Проверка уникальности email с учетом шифрования в secure.db
    // Так как email хранится в зашифрованном виде с рандомным IV, прямое сравнение невозможно.
    // Выполним выборку email-ов и сравним после дешифрования.
    $stmt = $pdo->query("SELECT id, email FROM user WHERE email IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $de = $db->decrypt($row['email'] ?? '');
        if ($de !== null && $de !== '' && hash_equals($de, $email)) {
            http_response_code(409);
            exit(json_encode(['success' => false, 'message' => 'Email уже зарегистрирован']));
        }
    }
    
    // Создание пользователя
    if ($db->createUser($login, $password, $email)) {
        $session->set('page', 'login');
        exit(json_encode(['success' => true, 'message' => 'Регистрация успешна']));
    } else {
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Ошибка регистрации']));
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера']));
}