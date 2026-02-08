<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/messages_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}

ensure_chat_schema($pdo);
$role = $_SESSION['user_role'] ?? 'user';
$recipient = ($role === 'owner') ? 'owner' : 'user';

try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE recipient_role = ? AND is_read = 0');
    $stmt->execute([$recipient]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['ok' => true, 'count' => $count]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'count' => 0]);
}
