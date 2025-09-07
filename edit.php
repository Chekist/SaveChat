<?php
/* edit.php – форма редактирования профиля */
?>

<form action="updateProfile.php" method="post" enctype="multipart/form-data">
  <div class="form-group">
    <label class="form-label">Логин</label>
    <input class="form-input" name="login" value="<?=htmlspecialchars($u['login'])?>" readonly>
  </div>
  
  <div class="form-group">
    <label class="form-label">Email</label>
    <input class="form-input" type="email" name="email" value="<?=htmlspecialchars($u['email'] ?? '')?>">
  </div>
  
  <div class="form-group">
    <label class="form-label">О себе</label>
    <textarea class="form-input" name="about" rows="3" placeholder="Расскажите о себе"><?=htmlspecialchars($u['about'] ?? '')?></textarea>
  </div>
  
  <div class="form-group">
    <label class="form-label">Фото профиля</label>
    <input class="form-input" type="file" name="photo" accept="image/*">
  </div>
  
  <div class="form-group">
    <label class="form-label">Новый пароль (оставьте пустым, если не хотите менять)</label>
    <input class="form-input" type="password" name="new_password">
  </div>
  
  <button class="btn btn-primary" style="width: 100%;">Сохранить изменения</button>
</form>