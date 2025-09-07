<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/SecurityHelper.php';

$session = SessionManager::getInstance();
SecurityHelper::ensureXsrfCookie(); // установим XSRF cookie для double-submit
// Лимит попыток: 5 за 5 минут (используем статический метод)

// Если пользователь уже авторизован, перенаправляем в личный кабинет
if ($session->isLoggedIn()) {
    header('Location: /?page=cabinet');
    exit;
}

// Проверяем лимит попыток входа
try {
    RateLimiter::requireLimit('login_attempts');
} catch (RateLimitExceededException $e) {
    $remainingTime = $e->getRetryAfter();
    $errorMessage = "Слишком много попыток входа. Пожалуйста, попробуйте снова через {$remainingTime} секунд.";
}
?>

<div class="container">
  <div style="max-width: 400px; margin: 2rem auto;">
    <div class="card">
      <div class="card-body">
        <h2 class="card-title text-center mb-3">Вход в аккаунт</h2>
        
        <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        
        <form class="login-form" method="post" action="login_handler.php" id="loginForm" autocomplete="on">
          <input type="hidden" name="login_attempt" value="1">
          <input type="hidden" name="action" value="login">
          
          <div class="form-group">
            <label for="login" class="form-label">Логин</label>
            <input 
                class="form-input" 
                type="text" 
                id="login" 
                name="login" 
                required 
                minlength="3" 
                maxlength="50"
                pattern="[a-zA-Z0-9_\-\.@]+"
                title="Допустимы только латинские буквы, цифры, символы _ - . @"
                autocomplete="username"
                <?= isset($login) ? 'value="' . htmlspecialchars($login, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
            >
          </div>
          
          <div class="form-group">
            <label for="password" class="form-label">Пароль</label>
            <div class="password-input-container">
                <input 
                    class="form-input" 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    minlength="8"
                    maxlength="100"
                    autocomplete="current-password"
                    pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                    title="Пароль должен содержать не менее 8 символов, включая заглавные и строчные буквы и цифры"
                >
                <button type="button" class="toggle-password" aria-label="Показать/скрыть пароль">
                    <i class="eye-icon">👁️</i>
                </button>
            </div>
            <div class="password-strength" id="password-strength"></div>
          </div>
          
          <div class="form-group">
            <div class="d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                    <label class="form-check-label" for="remember">Запомнить меня</label>
                </div>
                <a href="/?page=forgot-password" class="text-primary">Забыли пароль?</a>
            </div>
          </div>
          
          <button 
              class="btn btn-primary" 
              type="submit" 
              id="login-button"
              style="width: 100%;"
              <?= isset($errorMessage) ? 'disabled' : '' ?>>
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
              <span class="button-text">Войти</span>
          </button>
          
          <div id="error-message" class="alert alert-danger mt-3 d-none"></div>
          
          <?php if (isset($_GET['redirect'])): ?>
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'], ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>
        </form>
        
        <div class="text-center mt-3">
          <p class="text-gray">Нет аккаунта? <a href="/?page=register" class="text-primary">Зарегистрироваться</a></p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.password-input-container {
    position: relative;
    display: flex;
    align-items: center;
}

.toggle-password {
    position: absolute;
    right: 10px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    font-size: 1.2em;
    color: #666;
}

.password-strength {
    height: 4px;
    margin-top: 5px;
    border-radius: 2px;
    transition: all 0.3s ease;
}

.strength-0 { width: 20%; background-color: #ff4444; }
.strength-1 { width: 40%; background-color: #ffbb33; }
.strength-2 { width: 60%; background-color: #ffbb33; }
.strength-3 { width: 80%; background-color: #00C851; }
.strength-4 { width: 100%; background-color: #00C851; }

.spinner-border {
    margin-right: 8px;
}

.d-none {
    display: none !important;
}
</style>

<script>
// Включение/отключение кнопки в зависимости от валидности формы
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const loginInput = document.getElementById('login');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.querySelector('.toggle-password');
    const passwordStrength = document.getElementById('password-strength');
    const loginButton = document.getElementById('login-button');
    const errorMessage = document.getElementById('error-message');
    
    // Валидация формы при вводе
    function validateForm() {
        const isFormValid = form.checkValidity();
        loginButton.disabled = !isFormValid;
        return isFormValid;
    }
    
    // Обработка отправки формы
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        
        if (!validateForm()) {
            return;
        }
        
        // Показываем индикатор загрузки
        loginButton.disabled = true;
        const spinner = loginButton.querySelector('.spinner-border');
        const buttonText = loginButton.querySelector('.button-text');
        
        spinner.classList.remove('d-none');
        buttonText.textContent = 'Вход...';
        errorMessage.classList.add('d-none');
        
        try {
            const formData = new FormData(form);
            // Читаем XSRF cookie (double-submit)
            const xsrfCookie = document.cookie
              .split('; ')
              .find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || '';

            const response = await fetch('login_handler.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': xsrfCookie
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Перенаправляем после успешного входа
                window.location.href = data.redirect || '/?page=cabinet';
            } else {
                // Показываем сообщение об ошибке
                errorMessage.textContent = data.message || 'Произошла ошибка при входе';
                errorMessage.classList.remove('d-none');
                
                // Обновляем капчу, если она есть в ответе
                if (data.captchaHtml) {
                    const captchaContainer = document.getElementById('captcha-container');
                    if (captchaContainer) {
                        captchaContainer.innerHTML = data.captchaHtml;
                    }
                }
                
                // Анимация ошибки
                form.classList.add('shake');
                setTimeout(() => form.classList.remove('shake'), 500);
            }
        } catch (error) {
            console.error('Ошибка при входе:', error);
            errorMessage.textContent = 'Произошла ошибка при отправке запроса';
            errorMessage.classList.remove('d-none');
        } finally {
            // Восстанавливаем кнопку
            loginButton.disabled = false;
            spinner.classList.add('d-none');
            buttonText.textContent = 'Войти';
        }
    });
    
    // Валидация при изменении полей
    [loginInput, passwordInput].forEach(input => {
        input.addEventListener('input', validateForm);
    });
    
    // Переключение видимости пароля
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.querySelector('i').textContent = type === 'password' ? '👁️' : '👁️‍🗨️';
        });
    }
    
    // Индикатор сложности пароля
    if (passwordInput && passwordStrength) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Длина пароля
            if (password.length >= 8) strength++;
            
            // Содержит цифры
            if (/\d/.test(password)) strength++;
            
            // Содержит буквы в нижнем регистре
            if (/[a-z]/.test(password)) strength++;
            
            // Содержит буквы в верхнем регистре
            if (/[A-Z]/.test(password)) strength++;
            
            // Содержит специальные символы
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Ограничиваем максимальную сложность
            strength = Math.min(4, strength);
            
            // Обновляем индикатор
            passwordStrength.className = 'password-strength';
            passwordStrength.classList.add(`strength-${strength}`);
        });
    }
    
    // Инициализация валидации
    validateForm();
});
</script>