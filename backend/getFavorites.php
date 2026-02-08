<?php
// backend/getFavorites.php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/favorites_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['ok' => true, 'favorites' => []]);
    exit;
}

try {
    ensure_favorites_schema($pdo);
    $stmt = $pdo->prepare('SELECT pg_id AS id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'favorites' => $favorites]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'favorites' => [], 'error' => 'server_error']);
}
?>
