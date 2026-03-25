<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/reviews_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = trim($_POST['comment'] ?? '');

if ($pgId <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

try {
    ensure_reviews_schema($pdo);
    $existingStmt = $pdo->prepare('SELECT id, COALESCE(edit_count, 0) AS edit_count FROM reviews WHERE user_id = ? AND pg_id = ? ORDER BY id DESC LIMIT 1');
    $existingStmt->execute([$userId, $pgId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        if ((int)$existing['edit_count'] >= 1) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'review_edit_limit', 'message' => 'You already edited this review once.']);
            exit;
        }
        $stmt = $pdo->prepare('UPDATE reviews SET rating = ?, comment = ?, edit_count = edit_count + 1, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$rating, $comment, (int)$existing['id']]);
        echo json_encode(['ok' => true, 'message' => 'Review updated successfully.']);
    } else {
        $stmt = $pdo->prepare('INSERT INTO reviews (user_id, pg_id, rating, comment) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $pgId, $rating, $comment]);
        echo json_encode(['ok' => true, 'message' => 'Review submitted successfully.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => 'Unable to save review right now.']);
}
