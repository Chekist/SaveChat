<?php
$file = $_GET['f'] ?? '';
if ($file === '' || strpos($file, '..') !== false) {
    http_response_code(404);
    exit('Bad path');
}

/* абсолютный путь к uploads/ */
$path = __DIR__ . '/uploads/' . ltrim($file, '/');

if (!is_file($path)) {
    http_response_code(404);
    exit('Real path: ' . $path . '  – file not found');
}

header('Content-type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);