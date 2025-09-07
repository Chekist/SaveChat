<?php
declare(strict_types=1);

class FileUploadHandler {
    private array $allowedTypes = [
        'image' => ['image/jpeg', 'image/png', 'image/gif'],
        'video' => ['video/mp4', 'video/quicktime', 'video/3gpp'],
        'audio' => ['audio/ogg', 'audio/mpeg', 'audio/wav'],
        'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    ];
    
    private array $maxSizes = [
        'image' => 5242880,    // 5MB
        'video' => 52428800,   // 50MB
        'audio' => 10485760,   // 10MB
        'document' => 20971520 // 20MB
    ];

    public function __construct(private string $uploadDir = 'uploads') {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function upload(array $file, string $type, string $subdir = ''): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed with error code ' . $file['error']);
        }

        // Проверка MIME типа
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!isset($this->allowedTypes[$type]) || !in_array($mimeType, $this->allowedTypes[$type])) {
            throw new RuntimeException('Invalid file type');
        }

        // Проверка размера
        if ($file['size'] > ($this->maxSizes[$type] ?? 5242880)) {
            throw new RuntimeException('File too large');
        }

        // Генерация безопасного имени файла
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName = sprintf(
            '%s_%s.%s',
            uniqid($type . '_', true),
            bin2hex(random_bytes(8)),
            $ext
        );

        // Создание поддиректории если указана
        $targetDir = $this->uploadDir;
        if ($subdir) {
            $targetDir .= '/' . trim($subdir, '/');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }

        $targetPath = $targetDir . '/' . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to move uploaded file');
        }

        return str_replace('\\', '/', $targetPath);
    }
}
