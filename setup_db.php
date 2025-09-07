<?php
/**
 * Скрипт инициализации локальной SQLite базы данных
 * Заменяет старую MySQL базу на защищенную локальную
 */

require_once __DIR__ . '/LocalDatabase.php';

try {
    $localDb = LocalDatabase::getInstance();
    $db = $localDb->getConnection();
    
    // Создаем тестового администратора
    $adminExists = $db->prepare("SELECT id FROM user WHERE login = 'admin'");
    $adminExists->execute();
    
    if (!$adminExists->fetch()) {
        $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? bin2hex(random_bytes(8));
        $localDb->createUser('admin', $adminPassword, 'admin@example.com');
        echo "✅ Тестовый пользователь создан: admin/$adminPassword\n";
    } else {
        echo "ℹ️ Администратор уже существует\n";
    }
    
    echo "✅ Локальная база данных готова к работе!\n";
    
} catch (Exception $e) {
    error_log('Setup error: ' . $e->getMessage());
    http_response_code(500);
    echo "❌ Ошибка инициализации базы данных";
}
?>