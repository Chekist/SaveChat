<?php
/**
 * Скрипт для исправления групповых чатов
 */
require_once __DIR__ . '/LocalDatabase.php';

try {
    echo "Исправление групповых чатов...\n";
    
    $db = LocalDatabase::getInstance();
    $connection = $db->getConnection();
    
    // Проверяем существующие группы без участников
    $stmt = $connection->prepare("
        SELECT DISTINCT m.chat_id, m.user_id 
        FROM messages m 
        WHERE m.chat_id LIKE 'group_%' 
        AND NOT EXISTS (
            SELECT 1 FROM chat_members cm 
            WHERE cm.chat_id = m.chat_id AND cm.user_id = m.user_id
        )
    ");
    $stmt->execute();
    $orphanMessages = $stmt->fetchAll();
    
    echo "Найдено " . count($orphanMessages) . " сообщений без участников\n";
    
    foreach ($orphanMessages as $msg) {
        // Добавляем пользователя как участника группы
        $insertStmt = $connection->prepare("
            INSERT OR IGNORE INTO chat_members (chat_id, user_id, role) 
            VALUES (?, ?, ?)
        ");
        
        // Первый пользователь в группе становится админом
        $checkAdminStmt = $connection->prepare("
            SELECT COUNT(*) as admin_count 
            FROM chat_members 
            WHERE chat_id = ? AND role = 'admin'
        ");
        $checkAdminStmt->execute([$msg['chat_id']]);
        $adminCount = $checkAdminStmt->fetch()['admin_count'];
        
        $role = ($adminCount == 0) ? 'admin' : 'member';
        $insertStmt->execute([$msg['chat_id'], $msg['user_id'], $role]);
        
        echo "Добавлен участник {$msg['user_id']} в группу {$msg['chat_id']} как {$role}\n";
    }
    
    // Создаем записи в chat для групп
    $stmt = $connection->prepare("
        SELECT DISTINCT chat_id 
        FROM chat_members 
        WHERE chat_id LIKE 'group_%' 
        AND NOT EXISTS (SELECT 1 FROM chat WHERE chat.chat_id = chat_members.chat_id)
    ");
    $stmt->execute();
    $groupsWithoutChat = $stmt->fetchAll();
    
    foreach ($groupsWithoutChat as $group) {
        $insertChatStmt = $connection->prepare("
            INSERT INTO chat (chat_id, type) VALUES (?, 'group')
        ");
        $insertChatStmt->execute([$group['chat_id']]);
        echo "Создана запись в chat для группы {$group['chat_id']}\n";
    }
    
    echo "✓ Исправление завершено успешно\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка исправления: " . $e->getMessage() . "\n";
}
?>