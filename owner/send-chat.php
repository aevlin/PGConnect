<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php'); exit;
}

ensure_chat_schema($pdo);
ensure_system_schema($pdo);
$ownerId = (int)$_SESSION['user_id'];
$convId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$message = trim($_POST['message'] ?? '');
if (!$convId || $message === '') { header('Location: chat.php'); exit; }

// verify conversation belongs to owner
$c = $pdo->prepare("SELECT id, user_id, admin_id, COALESCE(conversation_type, 'tenant_owner') AS conversation_type FROM conversations WHERE id = ? AND owner_id = ? LIMIT 1");
$c->execute([$convId, $ownerId]);
$conv = $c->fetch(PDO::FETCH_ASSOC);
if (!$conv) { header('Location: chat.php'); exit; }

$recipientRole = (($conv['conversation_type'] ?? 'tenant_owner') === 'admin_owner') ? 'admin' : 'user';

chat_insert_message($pdo, [
    'conversation_id' => $convId,
    'sender_id' => $ownerId,
    'sender_role' => 'owner',
    'recipient_role' => $recipientRole,
    'message' => $message,
]);
try {
    if ($recipientRole === 'user') {
        $userId = (int)($conv['user_id'] ?? 0);
        if ($userId > 0) notify_user($pdo, $userId, 'user', 'New chat reply', 'Owner replied in your PG chat.', base_url('user/chat.php?c=' . $convId));
    } else {
        $adminId = (int)($conv['admin_id'] ?? 0);
        if ($adminId > 0) notify_user($pdo, $adminId, 'admin', 'Owner replied', 'Owner replied in admin chat.', base_url('admin/chat.php?c=' . $convId));
    }
    audit_log($pdo, 'chat_message_sent', 'conversation', (int)$convId, 'sender=owner');
} catch (Throwable $e) {}

header('Location: chat.php?c=' . $convId); exit;
