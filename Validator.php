<?php
/**
 * Класс для валидации входных данных
 */
class Validator {
    
    public static function sanitizeString($input, $maxLength = null) {
        $cleaned = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        if ($maxLength && strlen($cleaned) > $maxLength) {
            throw new InvalidArgumentException("String too long (max: $maxLength)");
        }
        return $cleaned;
    }
    
    public static function validateEmail($email) {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }
        return $email;
    }
    
    public static function validatePassword($password) {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException("Password must be at least 8 characters");
        }
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
            throw new InvalidArgumentException("Password must contain uppercase, lowercase and digit");
        }
        return $password;
    }
    
    public static function validateInteger($value, $min = null, $max = null) {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            throw new InvalidArgumentException("Invalid integer");
        }
        if ($min !== null && $int < $min) {
            throw new InvalidArgumentException("Value too small (min: $min)");
        }
        if ($max !== null && $int > $max) {
            throw new InvalidArgumentException("Value too large (max: $max)");
        }
        return $int;
    }
    
    public static function validateFile($file, $allowedTypes = [], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException("File upload error: " . $file['error']);
        }
        
        if ($file['size'] > $maxSize) {
            throw new InvalidArgumentException("File too large (max: " . ($maxSize/1024/1024) . "MB)");
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            throw new InvalidArgumentException("File type not allowed");
        }
        
        return true;
    }
    
    public static function validateChatId($chatId) {
        return self::validateInteger($chatId, 1);
    }
    
    public static function validateUserId($userId) {
        return self::validateInteger($userId, 1);
    }
    
    public static function sanitizeFilename($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return substr($filename, 0, 255);
    }
}
?>