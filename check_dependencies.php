<?php
/**
 * Проверка зависимостей и конфигурации
 */

echo "=== Проверка зависимостей ===\n\n";

// Проверка PHP версии
echo "PHP версия: " . PHP_VERSION;
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo " ✓\n";
} else {
    echo " ✗ (требуется 7.4+)\n";
}

// Проверка расширений
$required_extensions = ['pdo', 'pdo_sqlite', 'openssl', 'json', 'session'];
echo "\nРасширения PHP:\n";
foreach ($required_extensions as $ext) {
    echo "- $ext: " . (extension_loaded($ext) ? "✓" : "✗") . "\n";
}

// Проверка файлов конфигурации
echo "\nФайлы конфигурации:\n";
$config_files = ['.env', 'Config.php', 'SessionManager.php', 'LocalDatabase.php', 'Validator.php', 'RateLimiter.php'];
foreach ($config_files as $file) {
    echo "- $file: " . (file_exists(__DIR__ . '/' . $file) ? "✓" : "✗") . "\n";
}

// Проверка директорий
echo "\nДиректории:\n";
$directories = ['data', 'uploads', 'uploads/files', 'uploads/msg', 'uploads/video', 'uploads/voice'];
foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "- $dir: создана ✓\n";
    } else {
        echo "- $dir: ✓\n";
    }
}

// Проверка прав доступа
echo "\nПрава доступа:\n";
$writable_dirs = ['data', 'uploads'];
foreach ($writable_dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    echo "- $dir: " . (is_writable($path) ? "✓" : "✗") . "\n";
}

// Проверка конфигурации
echo "\nКонфигурация:\n";
try {
    require_once __DIR__ . '/Config.php';
    Config::load();
    echo "- .env файл: ✓\n";
    
    $dbConfig = Config::getDbConfig();
    echo "- База данных: " . $dbConfig['host'] . "/" . $dbConfig['name'] . " ✓\n";
    
} catch (Exception $e) {
    echo "- Конфигурация: ✗ " . $e->getMessage() . "\n";
}

echo "\n=== Проверка завершена ===\n";
?>