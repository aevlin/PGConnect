<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_schema.php';

ensure_session_started();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'items' => []]);
    exit;
}

ensure_system_schema($pdo);
try {
    $stmt = $pdo->prepare('SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? AND user_role = ? ORDER BY created_at DESC LIMIT 15');
    $stmt->execute([(int)$_SESSION['user_id'], (string)($_SESSION['user_role'] ?? 'user')]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'items' => []]);
}

