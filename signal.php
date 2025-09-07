<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'register':
        $username = $_POST['username'];
        $publicKey = $_POST['public_key'];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, public_key, is_online) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE public_key = ?, is_online = 1");
        $stmt->execute([$username, $publicKey, $publicKey]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'send_signal':
        $from = $_POST['from'];
        $to = $_POST['to'];
        $type = $_POST['type'];
        $data = $_POST['data'];
        
        $stmt = $pdo->prepare("INSERT INTO signals (from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$from, $to, $type, $data]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'get_signals':
        $username = $_GET['username'];
        
        $stmt = $pdo->prepare("SELECT * FROM signals WHERE to_user = ? ORDER BY created_at ASC");
        $stmt->execute([$username]);
        $signals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Удаляем полученные сигналы
        // amazonq-ignore-next-line
        $pdo->prepare("DELETE FROM signals WHERE to_user = ?")->execute([$username]);
        
        echo json_encode($signals);
        break;
        
    case 'get_users':
        $stmt = $pdo->query("SELECT username, public_key FROM users WHERE is_online = 1");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
}
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'action' => $action ?? 'unknown'
    ]);
}
?>