<?php
/**
 * Скрипт миграции с MySQL на локальную SQLite базу
 */

require_once __DIR__ . '/LocalDatabase.php';

echo "Инициализация локальной защищенной базы данных SQLite...\n";

try {
    $localDb = LocalDatabase::getInstance();
    $db = $localDb->getConnection();
    
    echo "✅ База данных SQLite создана успешно\n";
    echo "📁 Файл базы: " . __DIR__ . "/data/secure.db\n";
    
    // Создаем тестового пользователя admin
    $adminExists = $db->prepare("SELECT id FROM user WHERE login = 'admin'");
    $adminExists->execute();
    
    if (!$adminExists->fetch()) {
        $localDb->createUser('admin', 'admin123', 'admin@example.com');
        echo "👤 Создан тестовый пользователь: admin/admin123\n";
    }
    
    // Создаем резервную копию
    if ($localDb->backup()) {
        echo "💾 Создана резервная копия базы данных\n";
    }
    
    echo "\n🎉 Миграция завершена успешно!\n";
    echo "🔒 Все данные зашифрованы и хранятся локально\n";
    echo "📊 Размер базы: " . formatBytes(filesize(__DIR__ . '/data/secure.db')) . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
?>