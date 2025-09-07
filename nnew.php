<?php
/* nnew.php – форма + список новостей */
$is_owner_tab = $is_owner_tab ?? false;
$author = $is_owner_tab ? $u['login'] : ($usr['login'] ?? $u['login']);
$news = [];

try {
    if ($is_owner_tab && ($u['status'] ?? '') === 'admin') {
        $stmt = $db->getConnection()->prepare("SELECT * FROM news ORDER BY datetime DESC");
        $stmt->execute();
    } else {
        $stmt = $db->getConnection()->prepare("SELECT * FROM news WHERE author = ? ORDER BY datetime DESC");
        $stmt->execute([$author]);
    }
    $news = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('News query error: ' . $e->getMessage());
    $news = [];
}
?>

<?php if($is_owner_tab || ($u['status'] ?? '') === 'admin'): ?>
  <button class="btn btn-primary mb-3" onclick="toggleNewsForm()">
    📰 Добавить новость
  </button>
  <div id="addNewsForm" style="display: none;" class="mb-4">
    <div class="card">
      <div class="card-body">
        <form action="news.php" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <input class="form-input" name="title" placeholder="Заголовок новости" required>
          </div>
          <div class="form-group">
            <textarea class="form-input" name="text" placeholder="Текст новости" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <input class="form-input" type="file" name="photo" accept="image/*">
          </div>
          <input type="hidden" name="author" value="<?=htmlspecialchars($author)?>">
          <button class="btn btn-primary">Опубликовать</button>
          <button type="button" class="btn btn-secondary" onclick="toggleNewsForm()">Отмена</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="grid">
<?php if (empty($news)): ?>
  <p class="text-center text-gray">Новостей пока нет</p>
<?php else: ?>
  <?php foreach($news as $row): ?>
    <div class="card">
      <?php if($row['nphoto']): ?>
        <img src="<?=htmlspecialchars($row['nphoto'])?>" style="width: 100%; height: 160px; object-fit: cover;">
      <?php endif; ?>
      <div class="card-body">
        <h5 class="card-title"><?=htmlspecialchars($row['title'])?></h5>
        <p><?=nl2br(htmlspecialchars($row['text']))?></p>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span class="badge" style="background: var(--gray-200); color: var(--gray-700);"><?=htmlspecialchars($row['status'])?></span>
          <small class="text-gray"><?=date('d.m.Y', strtotime($row['datetime']))?></small>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
function toggleNewsForm() {
  const form = document.getElementById('addNewsForm');
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>