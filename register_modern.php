<?php require_once __DIR__ . '/SecurityHelper.php'; SecurityHelper::ensureXsrfCookie(); ?>
<div class="container">
  <div style="max-width: 400px; margin: 2rem auto;">
    <div class="card">
      <div class="card-body">
        <h2 class="card-title text-center mb-3">Регистрация</h2>
        
        <form class="register-form" method="post" action="reg.php">
          <input type="hidden" name="csrf_token" value="<?= SecurityHelper::generateCSRFToken() ?>">
          <div class="form-group">
            <label class="form-label">Логин</label>
            <input class="form-input" type="text" name="login" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">Пароль</label>
            <input class="form-input" type="password" name="password" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">Повторите пароль</label>
            <input class="form-input" type="password" name="repeat_password" required>
          </div>
          
          <button class="btn btn-primary" type="submit" style="width: 100%;">Зарегистрироваться</button>
          
          <div id="error-message" style="color: var(--danger); margin-top: 1rem; text-align: center;"></div>
        </form>
        
        <div class="text-center mt-3">
          <p class="text-gray">Уже есть аккаунт? <a href="/?page=login" class="text-primary">Войти</a></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const form = document.querySelector('.register-form');
form.addEventListener('submit', function(event) {
  event.preventDefault();
  const login = document.querySelector('input[name="login"]').value;  
  const password = document.querySelector('input[name="password"]').value;
  const repeatPassword = document.querySelector('input[name="repeat_password"]').value;
  const email = document.querySelector('input[name="email"]').value;
  const csrfToken = document.querySelector('input[name="csrf_token"]').value;

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'reg.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  // Read XSRF cookie and send as header (double-submit cookie pattern)
  const xsrfCookie = (document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN=')) || '').split('=')[1] || '';
  if (xsrfCookie) {
    xhr.setRequestHeader('X-CSRF-Token', xsrfCookie);
  }
  xhr.onreadystatechange = function() {
    if (xhr.readyState === XMLHttpRequest.DONE) {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            window.location.href = '/?page=login';
          } else {
            document.getElementById("error-message").textContent = response.message;
          }
        } catch (e) {
          document.getElementById("error-message").textContent = 'Ошибка обработки ответа сервера';
        }
      } else {
        console.error('Произошла ошибка при отправке данных на сервер');
      }
    }
  };
  xhr.send('login=' + encodeURIComponent(login) + '&password=' + encodeURIComponent(password) + '&email=' + encodeURIComponent(email) + '&repeat_password=' + encodeURIComponent(repeatPassword) + '&csrf_token=' + encodeURIComponent(csrfToken));
});
</script>