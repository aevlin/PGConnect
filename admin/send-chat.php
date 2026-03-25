<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/auth.php';
require_role('admin');

ensure_chat_schema($pdo);
$convId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$recipientRole = 'owner';
$message = trim($_POST['message'] ?? '');
if (!$convId || $message === '') {
    header('Location: chat.php'); exit;
}

$c = $pdo->prepare("SELECT id, owner_id FROM conversations WHERE id = ? AND admin_id = ? AND COALESCE(conversation_type, 'tenant_owner') = 'admin_owner' LIMIT 1");
$c->execute([$convId, (int)$_SESSION['user_id']]);
$conv = $c->fetch(PDO::FETCH_ASSOC);
if (!$conv || (int)($conv['owner_id'] ?? 0) <= 0) { header('Location: chat.php'); exit; }

$m = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, sender_role, recipient_role, message, is_read, is_read_admin) VALUES (?, ?, ?, ?, ?, 0, 1)');
$m->execute([$convId, (int)$_SESSION['user_id'], 'admin', $recipientRole, $message]);

header('Location: chat.php?c=' . $convId); exit;
