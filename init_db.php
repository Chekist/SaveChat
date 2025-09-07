<?php
/**
 * Инициализация базы данных
 */
require_once __DIR__ . '/LocalDatabase.php';

try {
    echo "Инициализация базы данных...\n";
    
    $db = LocalDatabase::getInstance();
    echo "✓ База данных SQLite создана успешно\n";
    
    // Создаем тестового пользователя
    $testUser = $db->findUser('admin');
    if (!$testUser) {
        $result = $db->createUser('admin', 'Admin123!', 'admin@test.com');
        if ($result) {
            echo "✓ Тестовый пользователь 'admin' создан (пароль: Admin123!)\n";
        }
    } else {
        echo "✓ Тестовый пользователь уже существует\n";
    }
    
    echo "✓ Инициализация завершена успешно\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка инициализации: " . $e->getMessage() . "\n";
}
?>