<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Config.php';

$session = SessionManager::getInstance();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Метод не разрешен']));
}

try {
    // Проверка CSRF токена
    require_once __DIR__ . '/SecurityHelper.php';
    if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'CSRF токен недействителен']));
    }
    
    // Проверка лимита попыток входа
    RateLimiter::requireLimit('login');
    
    $login = Validator::sanitizeString($_POST['login'] ?? '', 50);
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Заполните все поля']));
    }
    
    if (strlen($password) > 255) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Пароль слишком длинный']));
    }
    
    $db = LocalDatabase::getInstance();
    $user = $db->findUser($login);
    
    if (!$user) {
        // Защита от timing attacks
        password_hash('dummy', PASSWORD_DEFAULT);
        RateLimiter::recordAttempt('login');
        http_response_code(401);
        exit(json_encode(['success' => false, 'message' => 'Неверные учетные данные']));
    }
    
    $passwordSalt = Config::getPasswordSalt();
    $isValidPassword = password_verify($password . $passwordSalt, $user['password']);
    $isValidSaveCode = !empty($user['savecode']) && hash_equals((string)$user['savecode'], $password);
    
    if ($isValidPassword || $isValidSaveCode) {
        session_regenerate_id(true);
        $session->set('user', (int)$user['id']);
        $session->set('page', 'cabinet');
        
        // Очищаем код безопасности после использования
        if ($isValidSaveCode) {
            $db->getConnection()->prepare("UPDATE user SET savecode = NULL WHERE id = ?")
               ->execute([$user['id']]);
        }
        
        // Сбрасываем счетчик при успешном входе
        (new RateLimiter('login'))->reset();
        exit(json_encode(['success' => true, 'message' => 'Вход выполнен']));
    }
    
    RateLimiter::recordAttempt('login');
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Неверные учетные данные']));
    
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера']));
}
