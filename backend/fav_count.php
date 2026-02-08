<?php
// backend/fav_count.php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/favorites_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}
try {
    ensure_favorites_schema($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['ok' => true, 'count' => $count]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'count' => 0, 'error' => $e->getMessage()]);
}
