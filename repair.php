<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Метод не разрешен']));
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $login = Validator::sanitizeString($input['login'] ?? '', 50);
    
    if (empty($login)) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'message' => 'Логин не указан']));
    }
    
    $db = LocalDatabase::getInstance();
    $user = $db->findUser($login);
    
    if (!$user || empty($user['email']) || empty($user['savecode'])) {
        // Не раскрываем информацию о существовании пользователя
        exit(json_encode(['success' => true, 'message' => 'Если пользователь существует, код отправлен']));
    }
    
    // Настройки почты из конфигурации
    $mailConfig = [
        'host' => Config::get('MAIL_HOST', 'localhost'),
        'port' => Config::get('MAIL_PORT', '587'),
        'from' => Config::get('MAIL_FROM', 'noreply@yoursite.com')
    ];
    
    $to = $user['email'];
    $subject = "Код безопасности для входа";
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Код безопасности</title>
        <style>
            body { font-family: Arial, sans-serif; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 10px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
            .code { border: 2px solid #007bff; padding: 15px; border-radius: 5px; background-color: #f8f9fa; font-size: 18px; font-weight: bold; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Код безопасности</h2>
            <p>Здравствуйте, ' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '!</p>
            <p>Вы запросили код безопасности для входа в систему.</p>
            <div class="code">' . htmlspecialchars($user['savecode'], ENT_QUOTES, 'UTF-8') . '</div>
            <p><small>Если вы не запрашивали этот код, проигнорируйте это письмо.</small></p>
        </div>
    </body>
    </html>';
    
    $headers = "From: " . $mailConfig['from'] . "\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // В режиме разработки просто логируем
    if (Config::get('APP_ENV', 'development') === 'development') {
        error_log("Recovery code for $login: " . $user['savecode']);
        exit(json_encode(['success' => true, 'message' => 'Код отправлен (проверьте логи сервера)']));
    }
    
    // Отправка письма
    if (mail($to, $subject, $message, $headers)) {
        exit(json_encode(['success' => true, 'message' => 'Код безопасности отправлен на email']));
    } else {
        error_log('Failed to send recovery email to: ' . $to);
        http_response_code(500);
        exit(json_encode(['success' => false, 'message' => 'Ошибка отправки письма']));
    }
    
} catch (Exception $e) {
    error_log('Recovery error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера']));
}
?>
