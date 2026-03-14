<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../backend/login.php'); exit; }

ensure_chat_schema($pdo);
ensure_system_schema($pdo);
$userId = (int)$_SESSION['user_id'];
$convId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$message = trim($_POST['message'] ?? '');
if (!$convId || $message === '') { header('Location: chat.php'); exit; }

// verify conversation belongs to user
$c = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ? LIMIT 1');
$c->execute([$convId, $userId]);
if (!$c->fetchColumn()) { header('Location: chat.php'); exit; }

$m = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, sender_role, recipient_role, message, is_read) VALUES (?, ?, ?, ?, ?, 0)');
$m->execute([$convId, $userId, 'user', 'owner', $message]);
try {
    $ow = $pdo->prepare('SELECT owner_id FROM conversations WHERE id = ? LIMIT 1');
    $ow->execute([$convId]);
    $ownerId = (int)$ow->fetchColumn();
    if ($ownerId > 0) notify_user($pdo, $ownerId, 'owner', 'New chat message', 'You received a new message from a user.', '/PGConnect/owner/chat.php?c=' . $convId);
    audit_log($pdo, 'chat_message_sent', 'conversation', (int)$convId, 'sender=user');
} catch (Throwable $e) {}

header('Location: chat.php?c=' . $convId); exit;
