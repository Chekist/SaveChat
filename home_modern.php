<div class="container">
  <div class="text-center mb-3">
    <h1 class="text-primary">Добро пожаловать в современный мессенджер</h1>
    <p class="text-gray">Безопасное общение с современным дизайном</p>
  </div>

  <div class="grid grid-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
        <h3 class="card-title">Безопасность</h3>
        <p>Сквозное шифрование всех сообщений</p>
      </div>
    </div>

    <div class="card">
      <div class="card-body text-center">
        <div style="font-size: 3rem; margin-bottom: 1rem;">⚡</div>
        <h3 class="card-title">Скорость</h3>
        <p>Мгновенная доставка сообщений</p>
      </div>
    </div>

    <div class="card">
      <div class="card-body text-center">
        <div style="font-size: 3rem; margin-bottom: 1rem;">🎨</div>
        <h3 class="card-title">Дизайн</h3>
        <p>Современный и интуитивный интерфейс</p>
      </div>
    </div>
  </div>

  <?php if (!$session->isLoggedIn()): ?>
  <div class="text-center mt-3">
    <h2 class="mb-2">Начните общение прямо сейчас</h2>
    <div style="display: flex; gap: 1rem; justify-content: center;">
      <a href="/?page=register" class="btn btn-primary">Создать аккаунт</a>
      <a href="/?page=login" class="btn btn-secondary">Войти</a>
    </div>
  </div>
  <?php endif; ?>
</div>