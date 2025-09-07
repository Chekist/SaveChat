<?php
/* ggalery.php – галерея пользователя */
$is_owner_tab = $is_owner_tab ?? false;
$author = $is_owner_tab ? $u['login'] : ($usr['login'] ?? $u['login']);

// Получаем фотографии из базы данных
$galleryPhotos = [];
$currentUserId = $gallery_user_id ?? ($usrid ?: $my_id);

try {
    $stmt = $db->getConnection()->prepare("
        SELECT filename, title, description, created_at 
        FROM gallery_photos 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$currentUserId]);
    $galleryPhotos = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Gallery error: ' . $e->getMessage());
}
?>

<?php if($is_owner_tab): ?>
  <button class="btn btn-primary mb-3" onclick="toggleGalleryForm()">
    🖼️ Добавить фото
  </button>
  <div id="addGalleryForm" style="display: none;" class="mb-4">
    <div class="card">
      <div class="card-body">
        <form action="gallery.php" method="post" enctype="multipart/form-data">
          <div class="form-group">
            <input class="form-input" name="title" placeholder="Название фото" required>
          </div>
          <div class="form-group">
            <textarea class="form-input" name="text" placeholder="Описание" rows="2"></textarea>
          </div>
          <div class="form-group">
            <input class="form-input" type="file" name="photo" accept="image/*" required>
          </div>
          <input type="hidden" name="author" value="<?=htmlspecialchars($author)?>">
          <button class="btn btn-primary">Загрузить</button>
          <button type="button" class="btn btn-secondary" onclick="toggleGalleryForm()">Отмена</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-3">
<?php if (empty($galleryPhotos)): ?>
  <p class="text-center text-gray">Фотографий пока нет</p>
<?php else: ?>
  <?php foreach($galleryPhotos as $photo): ?>
    <div class="card">
      <img src="gallery/user_<?=$currentUserId?>/<?=htmlspecialchars($photo['filename'])?>" style="width: 100%; height: 200px; object-fit: cover;">
      <div class="card-body">
        <h5><?=htmlspecialchars($photo['title'] ?: 'Без названия')?></h5>
        <?php if ($photo['description']): ?>
          <p class="text-gray"><?=htmlspecialchars($photo['description'])?></p>
        <?php endif; ?>
        <small class="text-gray"><?=date('d.m.Y', strtotime($photo['created_at']))?></small>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<script>
function toggleGalleryForm() {
  const form = document.getElementById('addGalleryForm');
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>