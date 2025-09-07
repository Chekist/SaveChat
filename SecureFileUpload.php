<?php
/**
 * Безопасная загрузка файлов
 */
class SecureFileUpload {
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const ALLOWED_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg'],
        'document' => ['application/pdf', 'text/plain']
    ];
    
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp4', 'webm', 'mov',
        'mp3', 'wav', 'ogg',
        'pdf', 'txt'
    ];
    
    private $uploadDir;
    
    public function __construct(string $uploadDir) {
        $this->uploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function uploadFile(array $file, string $subdir = ''): ?string {
        try {
            $this->validateFile($file);
            
            $subdir = $this->sanitizeSubdir($subdir);
            $targetDir = $this->uploadDir . $subdir;
            
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $filename = $this->generateSecureFilename($file['name']);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new RuntimeException('Ошибка перемещения файла');
            }
            
            // Устанавливаем безопасные права доступа
            chmod($targetPath, 0644);
            
            return $subdir . '/' . $filename;
            
        } catch (Exception $e) {
            error_log('File upload error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function validateFile(array $file): void {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Ошибка загрузки файла: ' . $file['error']);
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('Файл слишком большой');
        }
        
        if ($file['size'] === 0) {
            throw new InvalidArgumentException('Пустой файл');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            throw new InvalidArgumentException('Недопустимое расширение файла');
        }
        
        $isValidMime = false;
        foreach (self::ALLOWED_TYPES as $types) {
            if (in_array($mimeType, $types)) {
                $isValidMime = true;
                break;
            }
        }
        
        if (!$isValidMime) {
            throw new InvalidArgumentException('Недопустимый тип файла');
        }
        
        // Дополнительная проверка для изображений
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new InvalidArgumentException('Поврежденное изображение');
            }
        }
    }
    
    private function sanitizeSubdir(string $subdir): string {
        $subdir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subdir);
        return substr($subdir, 0, 50);
    }
    
    private function generateSecureFilename(string $originalName): string {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50);
        
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
    
    public function deleteFile(string $filePath): bool {
        $fullPath = $this->uploadDir . $filePath;
        
        if (!file_exists($fullPath)) {
            return false;
        }
        
        // Проверяем, что файл находится в разрешенной директории
        $realPath = realpath($fullPath);
        $realUploadDir = realpath($this->uploadDir);
        
        if (strpos($realPath, $realUploadDir) !== 0) {
            return false;
        }
        
        return unlink($fullPath);
    }
    
    public static function getFileType(string $filename): string {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return 'image';
        }
        if (in_array($extension, ['mp4', 'webm', 'mov'])) {
            return 'video';
        }
        if (in_array($extension, ['mp3', 'wav', 'ogg'])) {
            return 'audio';
        }
        
        return 'document';
    }
}
?>