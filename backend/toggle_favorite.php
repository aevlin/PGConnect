<?php
// backend/toggle_favorite.php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/favorites_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
if (!$pgId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pg_id_required']);
    exit;
}

try {
    ensure_favorites_schema($pdo);
    // check existing
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND pg_id = ? LIMIT 1');
    $stmt->execute([$userId, $pgId]);
    $exists = $stmt->fetchColumn();
    if ($exists) {
        $del = $pdo->prepare('DELETE FROM favorites WHERE id = ?');
        $del->execute([$exists]);
        echo json_encode(['ok' => true, 'action' => 'removed']);
    } else {
        $ins = $pdo->prepare('INSERT INTO favorites (user_id, pg_id) VALUES (?, ?)');
        $ins->execute([$userId, $pgId]);
        echo json_encode(['ok' => true, 'action' => 'added']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()]);
}
