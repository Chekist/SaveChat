<?php
/* ppost.php – форма + список постов */
$is_owner_tab = $is_owner_tab ?? false;
$author = $is_owner_tab ? $u['login'] : ($usr['login'] ?? $u['login']);
$posts = [];

try {
    if ($is_owner_tab && ($u['status'] ?? '') === 'admin') {
        $stmt = $db->getConnection()->prepare("SELECT * FROM posts ORDER BY datetime DESC");
        $stmt->execute();
    } else {
        $stmt = $db->getConnection()->prepare("SELECT * FROM posts WHERE author = ? ORDER BY datetime DESC");
        $stmt->execute([$author]);
    }
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Posts query error: ' . $e->getMessage());
    $posts = [];
}
?>

<?php if($is_owner_tab || $u['status']==='admin'): ?>
  <!-- кнопка раскрытия формы -->
  <button class="btn btn-primary mb-3" onclick="togglePostForm()">
    ✏️ Добавить пост
  </button>
  <div id="addPostForm" style="display: none;" class="mb-4">
    <div class="card">
      <div class="card-body">
        <form action="post.php" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <textarea class="form-input" name="text" placeholder="Текст поста" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <input class="form-input" type="file" name="photo" accept="image/*">
          </div>
          <input type="hidden" name="author" value="<?=htmlspecialchars($author)?>">
          <button class="btn btn-primary">Опубликовать</button>
          <button type="button" class="btn btn-secondary" onclick="togglePostForm()">Отмена</button>
        </form>
      </div>
    </div>
  </div>
  
  <script>
  function togglePostForm() {
    const form = document.getElementById('addPostForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  }
  </script>
<?php endif; ?>

<!-- список -->
<div class="grid">
<?php if (empty($posts)): ?>
  <p class="text-center text-gray">Постов пока нет</p>
<?php else: ?>
  <?php foreach($posts as $row): ?>
    <div class="card">
      <?php if($row['pphoto']): ?>
        <img src="<?=htmlspecialchars($row['pphoto'])?>" class="card-img-top" style="height:160px;object-fit:cover;">
      <?php endif; ?>
      <div class="card-body">
        <p class="mb-2"><?=nl2br(htmlspecialchars($row['text']))?></p>
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <span class="badge" style="background: var(--gray-200); color: var(--gray-700);"><?=htmlspecialchars($row['status'])?></span>
          <small class="text-gray"><?=date('d.m.Y',strtotime($row['datetime']))?></small>
        </div>
      </div>
      <?php if($is_owner_tab || ($u['status'] ?? '') === 'admin'): ?>
        <div class="card-footer" style="display: flex; gap: 0.5rem;">
          <form action="process_form.php" method="post" style="display: inline;">
            <input type="hidden" name="post_id" value="<?=$row['id']?>">
            <input type="hidden" name="table_name" value="posts">
            <input type="hidden" name="status" value="Принят">
            <button class="btn btn-sm" style="background: var(--success); color: white;">✓</button>
          </form>
          <form action="process_form.php" method="post" style="display: inline;">
            <input type="hidden" name="post_id" value="<?=$row['id']?>">
            <input type="hidden" name="table_name" value="posts">
            <input type="hidden" name="status" value="Отклонен">
            <button class="btn btn-sm" style="background: var(--danger); color: white;">✗</button>
          </form>
          <form action="process_form.php" method="post" style="display: inline;">
            <input type="hidden" name="post_id" value="<?=$row['id']?>">
            <input type="hidden" name="table_name" value="posts">
            <input type="hidden" name="status" value="Удалить">
            <button class="btn btn-sm" style="background: var(--gray-400); color: white;">🗑</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>