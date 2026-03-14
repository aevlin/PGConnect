<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/messages_schema.php';
require_once __DIR__ . '/system_schema.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/audit.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
$message = trim($_POST['message'] ?? '');
if ($pgId <= 0 || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

try {
    ensure_chat_schema($pdo);
    ensure_system_schema($pdo);
    // find owner and existing conversation
    $stmt = $pdo->prepare('SELECT owner_id FROM pg_listings WHERE id = ? LIMIT 1');
    $stmt->execute([$pgId]);
    $ownerId = (int)$stmt->fetchColumn();
    if (!$ownerId) {
        echo json_encode(['ok' => false, 'error' => 'owner_not_found']);
        exit;
    }
    $c = $pdo->prepare('SELECT id FROM conversations WHERE user_id = ? AND owner_id = ? AND pg_id = ? LIMIT 1');
    $c->execute([$userId, $ownerId, $pgId]);
    $convId = (int)$c->fetchColumn();
    if (!$convId) {
        $ins = $pdo->prepare('INSERT INTO conversations (user_id, owner_id, pg_id) VALUES (?, ?, ?)');
        $ins->execute([$userId, $ownerId, $pgId]);
        $convId = (int)$pdo->lastInsertId();
    }
    $m = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, sender_role, recipient_role, message, is_read) VALUES (?, ?, ?, ?, ?, 0)');
    $m->execute([$convId, $userId, 'user', 'owner', $message]);
    notify_user($pdo, $ownerId, 'owner', 'New chat message', 'You received a new message from a user.', '/PGConnect/owner/chat.php?c=' . $convId);
    audit_log($pdo, 'chat_message_sent', 'conversation', (int)$convId, 'sender=user');
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
