<?php
declare(strict_types=1);

require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/LocalDatabase.php';
require_once __DIR__ . '/SecurityHelper.php';

$session = SessionManager::getInstance();
$session->requireAuth();

try {
    $chatId = $session->get('active_chat');
    if (!$chatId) {
        exit('<p class="text-center text-gray">Чат не выбран</p>');
    }
    
    $db = LocalDatabase::getInstance();
    $currentUserId = $session->getUserId();
    
    // Для групповых чатов проверяем участие
    if (strpos($chatId, 'group_') === 0) {
        $stmt = $db->getConnection()->prepare("SELECT 1 FROM chat_members WHERE chat_id = ? AND user_id = ?");
        $stmt->execute([$chatId, $currentUserId]);
        if (!$stmt->fetch()) {
            exit('<p class="text-center text-danger">Вы не участник этой группы</p>');
        }
    }
    
    $stmt = $db->getConnection()->prepare("
        SELECT m.*, u.login as sender_login 
        FROM messages m
        LEFT JOIN user u ON m.user_id = u.id
        WHERE m.chat_id = ?
        ORDER BY m.timestamp ASC
        LIMIT 50
    ");
    $stmt->execute([$chatId]);
    $messages = $stmt->fetchAll();
    
    if (empty($messages)) {
        echo '<p class="text-center text-gray">Сообщений пока нет</p>';
        exit;
    }
    
    foreach ($messages as $msg) {
        $isOwn = (int)$msg['user_id'] === $currentUserId;
        $senderName = SecurityHelper::escapeHtml($msg['sender_login'] ?: 'Неизвестный');
        
        // Расшифровываем сообщение
        $messageText = '';
        $currentKey = $session->get('current_chat_key');
        
        if (!empty($msg['text_cipher'])) {
            if (!empty($currentKey)) {
                try {
                    $decrypted = $db->decrypt($msg['text_cipher']);
                    $parts = explode('::KEY::', $decrypted, 2);
                    if (count($parts) === 2 && $parts[1] === $currentKey) {
                        $messageText = SecurityHelper::escapeHtml($parts[0]);
                    } else {
                        $messageText = '🔒 Неверный ключ';
                    }
                } catch (Exception $e) {
                    $messageText = '🔒 Ошибка расшифровки';
                }
            } else {
                $messageText = '🔒 Введите ключ';
            }
        } else {
            $messageText = SecurityHelper::escapeHtml($msg['text'] ?: '');
        }
        
        $timestamp = date('H:i', strtotime($msg['timestamp']));
        
        echo '<div class="message' . ($isOwn ? ' own' : '') . '">';
        
        if (!$isOwn) {
            echo '<div class="message-avatar"></div>';
        }
        
        echo '<div class="message-bubble">';
        
        if (!$isOwn) {
            echo '<div style="font-size: 0.75rem; color: #666; margin-bottom: 0.25rem;">' . $senderName . '</div>';
        }
        
        if ($messageText) {
            $isEncrypted = !empty($msg['text_cipher']);
            if (!$isEncrypted && !empty($messageText)) {
                echo '<div style="border-left: 3px solid #ffa500; padding-left: 0.5rem; background: rgba(255,165,0,0.1);" title="⚠️ Незашифровано">';
                echo nl2br($messageText);
                echo '</div>';
            } else {
                echo '<div>' . nl2br($messageText) . '</div>';
            }
        }
        
        // Показываем файлы если есть
        if (!empty($msg['photo'])) {
            $photoPath = SecurityHelper::escapeHtml($msg['photo']);
            echo '<img src="' . $photoPath . '" alt="Image" style="max-width: 200px; border-radius: 0.5rem; margin-top: 0.5rem;">';
        }
        
        // Специальная обработка для звонков
        if ($msg['msg_type'] === 'call') {
            echo '<div class="call-message" style="background: var(--gray-100); padding: 0.5rem; border-radius: 0.5rem; margin-top: 0.25rem; font-style: italic;">';
            echo $messageText;
            echo '</div>';
        }
        
        echo '<div style="font-size: 0.7rem; opacity: 0.7; margin-top: 0.5rem; text-align: right; display: flex; justify-content: space-between; align-items: center;">';
        echo '<span>' . $timestamp;
        
        // Показываем статус прочтения для своих сообщений
        if ($isOwn) {
            $readStatus = $msg['is_read'] ? '✓✓' : '✓';
            $readColor = $msg['is_read'] ? '#4ade80' : 'rgba(255,255,255,0.7)';
            echo '<span style="margin-left: 0.5rem; color: ' . $readColor . ';">' . $readStatus . '</span>';
        }
        echo '</span>';
        
        // Кнопки редактирования для своих сообщений
        if ($isOwn && $msg['msg_type'] !== 'system') {
            echo '<div style="display: flex; gap: 0.25rem;">';
            echo '<button onclick="editMessage(' . $msg['id'] . ')" style="background: none; border: none; cursor: pointer; opacity: 0.5;" title="Редактировать">✏️</button>';
            echo '<button onclick="deleteMessage(' . $msg['id'] . ')" style="background: none; border: none; cursor: pointer; opacity: 0.5;" title="Удалить">🗑️</button>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    error_log('Get messages error: ' . $e->getMessage());
    echo '<p class="text-center text-danger">Ошибка загрузки сообщений</p>';
}
?>