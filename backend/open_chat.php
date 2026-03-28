<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/messages_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$role = $_SESSION['user_role'] ?? 'user';
if ($role !== 'user') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'user_only']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
if ($pgId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_pg']);
    exit;
}

try {
    ensure_chat_schema($pdo);

    $s = $pdo->prepare('SELECT owner_id FROM pg_listings WHERE id = ? LIMIT 1');
    $s->execute([$pgId]);
    $ownerId = (int)$s->fetchColumn();
    if ($ownerId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'owner_not_found']);
        exit;
    }

    $c = $pdo->prepare('SELECT id FROM conversations WHERE user_id = ? AND owner_id = ? AND pg_id = ? LIMIT 1');
    $c->execute([$userId, $ownerId, $pgId]);
    $conversationId = (int)$c->fetchColumn();

    if ($conversationId <= 0) {
        $ins = $pdo->prepare('INSERT INTO conversations (user_id, owner_id, pg_id) VALUES (?, ?, ?)');
        $ins->execute([$userId, $ownerId, $pgId]);
        $conversationId = (int)$pdo->lastInsertId();
    }

    echo json_encode([
        'ok' => true,
        'conversation_id' => $conversationId,
        'redirect' => base_url('user/chat.php?c=' . $conversationId)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
