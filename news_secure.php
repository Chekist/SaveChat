<?php
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/db.php';

$session = SessionManager::getInstance();
?>

<div class="container">
    <h2>Новости</h2>
    
    <?php if ($session->isLoggedIn()): ?>
        <div class="mb-3">
            <a href="/?page=new" class="btn btn-primary">Добавить новость</a>
        </div>
    <?php endif; ?>
    
    <div class="news-list">
        <?php
        try {
            $stmt = $db->prepare("
                SELECT n.*, u.login as author_login, u.id as author_id 
                FROM news n 
                LEFT JOIN user u ON n.author = u.login 
                WHERE n.status = 'Принят' 
                ORDER BY n.datetime DESC 
                LIMIT 20
            ");
            $stmt->execute();
            $newsList = $stmt->fetchAll();
            
            if (empty($newsList)) {
                echo '<p class="text-muted">Новостей пока нет</p>';
            } else {
                foreach ($newsList as $news) {
                    $safeTitle = SecurityHelper::escapeHtml($news['title']);
                    $safeText = SecurityHelper::escapeHtml($news['text']);
                    $safeAuthor = SecurityHelper::escapeHtml($news['author_login'] ?: $news['author']);
                    $safeDate = SecurityHelper::escapeHtml(date('d.m.Y H:i', strtotime($news['datetime'])));
                    $safePhoto = SecurityHelper::escapeHtml($news['nphoto'] ?: '');
                    
                    echo '<div class="card mb-3">';
                    echo '<div class="card-body">';
                    echo '<h5 class="card-title">' . $safeTitle . '</h5>';
                    
                    if (!empty($safePhoto)) {
                        echo '<img src="' . $safePhoto . '" class="img-fluid mb-3" alt="News image" style="max-height: 300px;">';
                    }
                    
                    echo '<p class="card-text">' . nl2br($safeText) . '</p>';
                    echo '<div class="text-muted small">';
                    echo 'Автор: ' . $safeAuthor . ' | ' . $safeDate;
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
            }
        } catch (Exception $e) {
            error_log('News loading error: ' . $e->getMessage());
            echo '<p class="text-danger">Ошибка загрузки новостей</p>';
        }
        ?>
    </div>
</div>

<style>
.news-list .card {
    transition: transform 0.2s;
}
.news-list .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>