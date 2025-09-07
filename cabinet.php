<?php
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/SecurityHelper.php';

$session = SessionManager::getInstance();
$session->requireAuth();

$db = LocalDatabase::getInstance();
$my_id = $session->getUserId();
$usrid = (int)($_GET['usrid'] ?? 0);

if ($usrid === $my_id && $usrid !== 0) { 
    header('Location: /?page=cabinet'); 
    exit; 
}

if (!$usrid) {
    $stmt = $db->getConnection()->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$my_id]);
    $u = $stmt->fetch();
    $is_owner = true;
} else {
    $stmt = $db->getConnection()->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$usrid]);
    $u = $stmt->fetch();
    $is_owner = false;
}

if (!$u) { 
    header('Location: /'); 
    exit; 
}

// Расшифровываем email если нужно
if (!empty($u['email'])) {
    $u['email'] = $db->decrypt($u['email']);
}

// Подключаем базу для совместимости со старыми файлами
// $conn = $db->getConnection(); // Не нужно - используем $db
?>
<div class="container">
  <div class="text-center mb-3">
    <img src="<?=htmlspecialchars($u['photo'] ?: 'img/default-avatar.svg')?>" onerror="this.src='img/default-avatar.svg'" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem;">
    <h1><?=htmlspecialchars($u['login'])?>
    <?php 
    $isOnline = false;
    if ($u['last_activity']) {
        $isOnline = (time() - strtotime($u['last_activity'])) < 300;
    }
    ?>
    <span style="color: <?= $isOnline ? '#10b981' : '#6b7280' ?>; margin-left: 0.5rem;"><?= $isOnline ? '🟢' : '🔴' ?></span>
    </h1>
    <p class="text-gray"><?=htmlspecialchars($u['about'] ?? 'Описание профиля')?></p>
    <p class="text-gray"><?= $isOnline ? 'Онлайн' : 'Офлайн' ?></p>
    <?php if($is_owner): ?>
      <button class="btn btn-secondary" onclick="toggleEdit()">Редактировать</button>
    <?php endif; ?>
  </div>

  <!-- Табы -->
  <div class="tabs">
    <button class="tab-btn active" onclick="showTab('posts')">Посты</button>
    <button class="tab-btn" onclick="showTab('news')">Новости</button>
    <button class="tab-btn" onclick="showTab('gallery')">Галерея</button>
    <?php if($is_owner): ?>
      <button class="tab-btn" onclick="showTab('warnings')">Предупреждения</button>
    <?php endif; ?>
  </div>

  <!-- Контент табов -->
  <div id="posts" class="tab-content active">
    <?php $is_owner_tab=$is_owner; include 'ppost.php'; ?>
  </div>

  <div id="news" class="tab-content">
    <?php include 'nnew.php'; ?>
  </div>

  <div id="gallery" class="tab-content">
    <?php 
    $gallery_user_id = $usrid ?: $my_id;
    include 'ggalery.php'; 
    ?>
  </div>

  <?php if($is_owner): ?>
  <div id="warnings" class="tab-content">
    <?php include 'warning.php'; ?>
  </div>
  <?php endif; ?>

  <!-- Форма редактирования -->
  <?php if($is_owner): ?>
  <div id="editForm" style="display: none;" class="mt-3">
    <div class="card">
      <div class="card-body">
        <h3 class="card-title">Редактирование профиля</h3>
        <?php include 'edit.php'; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  border-bottom: 1px solid var(--gray-200);
}
.tab-btn {
  padding: 0.75rem 1.5rem;
  border: none;
  background: none;
  color: var(--gray-600);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all 0.2s;
}
.tab-btn.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
}
.tab-content {
  display: none;
}
.tab-content.active {
  display: block;
}
.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}
.badge {
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}
</style>

<script>
function showTab(tabName) {
  // Скрываем все табы
  document.querySelectorAll('.tab-content').forEach(tab => {
    tab.classList.remove('active');
  });
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  
  // Показываем выбранный таб
  const targetTab = document.getElementById(tabName);
  const targetBtn = document.querySelector(`[onclick="showTab('${tabName}')"]`);
  
  if(targetTab) targetTab.classList.add('active');
  if(targetBtn) targetBtn.classList.add('active');
}

function toggleEdit() {
  const form = document.getElementById('editForm');
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>