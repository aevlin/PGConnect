<?php
header('Content-Type: application/json');
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/messages_schema.php';
require_once __DIR__ . '/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

ensure_chat_schema($pdo);
ensure_bookings_schema($pdo);

$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['user_role'] ?? 'user');
$convId = (int)($_GET['conversation_id'] ?? 0);
if ($convId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_conversation']);
    exit;
}

$sql = 'SELECT * FROM conversations WHERE id = ?';
$params = [$convId];
if ($role === 'user') {
    $sql .= ' AND user_id = ?';
    $params[] = $userId;
} elseif ($role === 'owner') {
    $sql .= ' AND owner_id = ?';
    $params[] = $userId;
}
$stmt = $pdo->prepare($sql . ' LIMIT 1');
$stmt->execute($params);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conversation) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$m = $pdo->prepare('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC, id ASC');
$m->execute([$convId]);
$messages = $m->fetchAll(PDO::FETCH_ASSOC);

if ($role === 'user') {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND recipient_role = 'user'")->execute([$convId]);
} elseif ($role === 'owner') {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND recipient_role = 'owner'")->execute([$convId]);
} elseif ($role === 'admin') {
    $pdo->prepare("UPDATE messages SET is_read_admin = 1 WHERE conversation_id = ? AND sender_role IN ('user','owner')")->execute([$convId]);
}

$booking = null;
if (!empty($conversation['pg_id']) && !empty($conversation['user_id'])) {
    $b = $pdo->prepare('SELECT id, status, payment_status, move_in_date, created_at, paid_at, moved_out_at FROM bookings WHERE user_id = ? AND pg_id = ? ORDER BY created_at DESC LIMIT 1');
    $b->execute([(int)$conversation['user_id'], (int)$conversation['pg_id']]);
    $booking = $b->fetch(PDO::FETCH_ASSOC) ?: null;
}

echo json_encode([
    'ok' => true,
    'messages' => $messages,
    'booking' => $booking,
    'role' => $role,
]);
