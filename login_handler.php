<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/LocalDatabase.php';

class LoginHandler {
    private $db;
    private $session;
    private $rateLimiter;
    
    public function __construct() {
        // Используем безопасную локальную БД (SQLite) в файле data/secure.db
        $this->db = LocalDatabase::getInstance()->getConnection();
        $this->session = SessionManager::getInstance();
        $this->rateLimiter = new RateLimiter('login_attempts', 5, 300);
    }
    
    public function handleRequest(): void {
        // Устанавливаем заголовок для JSON-ответа
        header('Content-Type: application/json');
        
        // Обрабатываем только POST-запросы
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Метод не поддерживается', 405);
            return;
        }
        
        // Проверяем CSRF-токен: принимаем ИЛИ стандартный сессионный токен, ИЛИ stateless XSRF (Double Submit Cookie)
        $csrfToken = $_POST['csrf_token'] ?? '';
        $headerXsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrfOk = SecurityHelper::validateCSRFToken($csrfToken) || SecurityHelper::validateXsrf($headerXsrf);
        if (!$csrfOk) {
            error_log('CSRF token validation failed. Token: ' . $csrfToken);
            error_log('Session token: ' . ($_SESSION['csrf_token'] ?? 'not set'));
            // В режиме отладки возвращаем дополнительную информацию для диагностики
            $isDebug = isset($_GET['debug']) && $_GET['debug'] === '1';
            if ($isDebug) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Недействительный CSRF-токен. Пожалуйста, обновите страницу и попробуйте снова.',
                    'debug' => [
                        'form_token' => $csrfToken,
                        'session_token' => $_SESSION['csrf_token'] ?? null,
                        'session_id' => session_id(),
                        'xsrf_cookie' => SecurityHelper::getXsrfCookie(),
                        'xsrf_header' => $headerXsrf,
                    ],
                ]);
                exit;
            }
            $this->sendError('Недействительный CSRF-токен. Пожалуйста, обновите страницу и попробуйте снова.', 403);
            return;
        }
        
        try {
            $this->processLogin();
        } catch (RateLimitExceededException $e) {
            $this->sendError(
                'Слишком много попыток входа. Пожалуйста, попробуйте снова через ' . $e->getRetryAfter() . ' секунд.',
                429
            );
        } catch (InvalidArgumentException $e) {
            $this->sendError($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            $this->sendError($e->getMessage(), 401);
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $this->sendError('Произошла ошибка при входе в систему', 500);
        }
    }
    
    private function processLogin(): void {
        // Проверяем лимит попыток входа
        $this->rateLimiter->requireLimit('login_attempts');
        
        // Получаем и валидируем данные из формы
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        
        // Валидация ввода
        if (empty($login) || empty($password)) {
            throw new InvalidArgumentException('Логин и пароль обязательны для заполнения');
        }
        
        // Поиск пользователя
        $user = $this->findUser($login);
        
        // Проверяем пароль
        if (!$user || !password_verify($password, $user['password'])) {
            // В текущей схеме нет полей для счетчиков попыток, просто возвращаем ошибку
            throw new RuntimeException('Неверный логин или пароль');
        }
        
        // Вход выполнен успешно
        $this->loginUser($user, $remember);
        
        // Возвращаем успешный ответ
        $this->sendSuccess([
            'redirect' => $_POST['redirect'] ?? '/?page=cabinet'
        ]);
    }
    
    private function findUser(string $login): ?array {
        // В текущей схеме таблица называется `user`, а логин хранится в поле `login`
        $stmt = $this->db->prepare('SELECT * FROM `user` WHERE `login` = :login OR `email` = :login LIMIT 1');
        $stmt->execute([':login' => $login]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function loginUser(array $user, bool $remember): void {
        // Устанавливаем данные пользователя в сессию
        $this->session->setUserId((int)$user['id']);
        
        // Опциональная логика remember-me опущена, т.к. таблица user_tokens может отсутствовать
    }
    
    // Методы работы с несуществующими полями/таблицами удалены для совместимости со схемой
    
    private function sendError(string $message, int $statusCode = 400): void {
        http_response_code($statusCode);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    private function sendSuccess(array $data = []): void {
        echo json_encode(array_merge(['success' => true], $data));
        exit;
    }
}

// Запускаем обработчик
$handler = new LoginHandler();
$handler->handleRequest();
