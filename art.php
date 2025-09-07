<?php
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/LocalDatabase.php';

$session = SessionManager::getInstance();
?>

<div class="container">
    <h1 class="text-center mb-3">Галерея</h1>
    
    <?php if ($session->isLoggedIn()): ?>
        <div class="text-center mb-3">
            <a href="/?page=upload" class="btn btn-primary">Добавить фото</a>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-3">
        <?php
        try {
            // Пока что показываем файлы из папки gallery
            $galleryDir = __DIR__ . '/gallery';
            if (is_dir($galleryDir)) {
                $files = array_diff(scandir($galleryDir), ['.', '..']);
                $imageFiles = array_filter($files, function($file) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                });
                
                if (empty($imageFiles)) {
                    echo '<div class="col-span-3 text-center text-gray">Фотографий пока нет</div>';
                } else {
                    foreach ($imageFiles as $file) {
                        $filePath = 'gallery/' . $file;
                        $fileName = pathinfo($file, PATHINFO_FILENAME);
                        $fileTime = filemtime($galleryDir . '/' . $file);
                        $dateString = date('d.m.Y H:i', $fileTime);
                        
                        echo '<div class="card">';
                        echo '<img src="' . SecurityHelper::sanitizeOutput($filePath) . '" alt="Gallery image" style="width: 100%; height: 200px; object-fit: cover;">';
                        echo '<div class="card-body">';
                        echo '<h5 class="card-title">' . SecurityHelper::sanitizeOutput($fileName) . '</h5>';
                        echo '<p class="text-gray small">Дата: ' . $dateString . '</p>';
                        echo '</div>';
                        echo '</div>';
                    }
                }
            } else {
                echo '<div class="col-span-3 text-center text-gray">Папка галереи не найдена</div>';
            }
        } catch (Exception $e) {
            error_log('Gallery loading error: ' . $e->getMessage());
            echo '<div class="col-span-3 text-center text-danger">Ошибка загрузки галереи</div>';
        }
        ?>
    </div>
</div>

<style>
.col-span-3 {
    grid-column: span 3;
}
@media (max-width: 768px) {
    .col-span-3 {
        grid-column: span 1;
    }
}
</style>