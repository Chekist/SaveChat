<?php
require_once __DIR__ . '/Config.php';

/**
 * Локальная защищенная база данных на SQLite с шифрованием
 */
class LocalDatabase {
    private static $instance = null;
    private $pdo;
    private $encryptionKey;
    
    private function __construct() {
        $dbPath = __DIR__ . '/data/secure.db';
        $this->encryptionKey = Config::getEncryptionKey();
        
        // Создаем директорию если не существует
        $dataDir = dirname($dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0700, true);
        }
        
        try {
            $this->pdo = new PDO("sqlite:$dbPath");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Включаем WAL режим для лучшей производительности
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
            
            $this->initTables();
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function initTables() {
        $tables = [
            'user' => "
                CREATE TABLE IF NOT EXISTS user (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    login TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    savecode TEXT,
                    photo TEXT DEFAULT 'img/default-avatar.svg',
                    status TEXT DEFAULT 'user',
                    about TEXT,
                    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
            'chat' => "
                CREATE TABLE IF NOT EXISTS chat (
                    chat_id TEXT PRIMARY KEY,
                    type TEXT DEFAULT 'dialog',
                    name TEXT,
                    avatar TEXT,
                    user_id1 INTEGER,
                    user_id2 INTEGER,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id1) REFERENCES user(id),
                    FOREIGN KEY (user_id2) REFERENCES user(id)
                )",
            'chat_members' => "
                CREATE TABLE IF NOT EXISTS chat_members (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    chat_id TEXT NOT NULL,
                    user_id INTEGER NOT NULL,
                    role TEXT DEFAULT 'member',
                    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(chat_id, user_id),
                    FOREIGN KEY (chat_id) REFERENCES chat(chat_id),
                    FOREIGN KEY (user_id) REFERENCES user(id)
                )",
            'group_settings' => "
                CREATE TABLE IF NOT EXISTS group_settings (
                    chat_id TEXT PRIMARY KEY,
                    name TEXT,
                    avatar TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
            'gallery_photos' => "
                CREATE TABLE IF NOT EXISTS gallery_photos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    filename TEXT NOT NULL,
                    title TEXT,
                    description TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES user(id)
                )",
            'messages' => "
                CREATE TABLE IF NOT EXISTS messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    chat_id TEXT NOT NULL,
                    text TEXT,
                    text_cipher TEXT,
                    photo TEXT,
                    msg_type TEXT DEFAULT 'text',
                    is_read INTEGER DEFAULT 0,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES user(id),
                    FOREIGN KEY (chat_id) REFERENCES chat(chat_id)
                )",
            'posts' => "
                CREATE TABLE IF NOT EXISTS posts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    text TEXT NOT NULL,
                    author TEXT NOT NULL,
                    pphoto TEXT,
                    status TEXT DEFAULT 'не принята',
                    datetime DATETIME DEFAULT CURRENT_TIMESTAMP
                )",
            'news' => "
                CREATE TABLE IF NOT EXISTS news (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    text TEXT NOT NULL,
                    author TEXT NOT NULL,
                    nphoto TEXT,
                    status TEXT DEFAULT 'не принята',
                    datetime DATETIME DEFAULT CURRENT_TIMESTAMP
                )"
        ];
        
        foreach ($tables as $name => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                error_log("Failed to create table $name: " . $e->getMessage());
                throw new RuntimeException("Database initialization failed for table: $name");
            }
        }
        
        // Миграции для новых полей
        $migrations = [
            "ALTER TABLE user ADD COLUMN about TEXT",
            "ALTER TABLE user ADD COLUMN last_activity DATETIME DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE messages ADD COLUMN is_read INTEGER DEFAULT 0"
        ];
        
        foreach ($migrations as $migration) {
            try {
                $this->pdo->exec($migration);
            } catch (PDOException $e) {
                // Поле уже существует
            }
        }
        
        // Создаем индексы для производительности
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_messages_chat ON messages(chat_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_user ON messages(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_chat_users ON chat(user_id1, user_id2)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (PDOException $e) {
                error_log("Failed to create index: " . $e->getMessage());
                // Индексы не критичны, продолжаем
            }
        }
    }
    
    // Шифрование чувствительных данных
    public function encrypt($data) {
        if (empty($data)) return $data;
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($data) {
        if (empty($data)) return $data;
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }
    
    // Безопасное сохранение пользователя
    public function createUser($login, $password, $email) {
        $salt = Config::getPasswordSalt();
        $hash = password_hash($password . $salt, PASSWORD_ARGON2ID);
        $code = random_int(100000000000, 999999999999);
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO user (login, password, email, savecode) VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$login, $hash, $this->encrypt($email), $code]);
    }
    
    // Поиск пользователя для входа
    public function findUser($login) {
        $stmt = $this->pdo->prepare("SELECT * FROM user WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        
        if ($user && $user['email']) {
            $user['email'] = $this->decrypt($user['email']);
        }
        
        return $user;
    }
    
    // Резервное копирование
    public function backup() {
        $backupDir = __DIR__ . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0700, true);
        }
        
        $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.db';
        return copy(__DIR__ . '/data/secure.db', $backupFile);
    }
}
?>