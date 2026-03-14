<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/system_schema.php';

ensure_session_started();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

ensure_system_schema($pdo);
try {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_role = ? AND is_read = 0');
    $stmt->execute([(int)$_SESSION['user_id'], (string)($_SESSION['user_role'] ?? 'user')]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
}

