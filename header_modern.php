<?php
require_once __DIR__ . '/SessionManager.php';
$session = SessionManager::getInstance();
$isLoggedIn = $session->isLoggedIn();
$currentPage = $_GET['page'] ?? 'home';
?>

<header class="header">
  <nav class="nav">
    <a href="/" class="logo">💬 Messenger</a>
    
    <ul class="nav-links">
      <li><a href="/?page=home" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>">Главная</a></li>
      <li><a href="/?page=news" class="nav-link <?= $currentPage === 'news' ? 'active' : '' ?>">Новости</a></li>
      <li><a href="/?page=art" class="nav-link <?= $currentPage === 'art' ? 'active' : '' ?>">Галерея</a></li>
      
      <?php if ($isLoggedIn): ?>
        <li><a href="/?page=msg" class="nav-link <?= $currentPage === 'msg' ? 'active' : '' ?>">Сообщения</a></li>
        <li><a href="/?page=cabinet" class="nav-link <?= $currentPage === 'cabinet' ? 'active' : '' ?>">Профиль</a></li>
      <?php endif; ?>
    </ul>
    
    <div class="nav-actions">
      <?php if ($isLoggedIn): ?>
        <a href="logout.php" class="btn btn-secondary">Выход</a>
      <?php else: ?>
        <a href="/?page=login" class="btn btn-secondary">Вход</a>
        <a href="/?page=register" class="btn btn-primary">Регистрация</a>
      <?php endif; ?>
    </div>
  </nav>
</header>