<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
require_once '../backend/auth.php';
require_role('user');

ensure_chat_schema($pdo);
ensure_system_schema($pdo);

$userId = (int)$_SESSION['user_id'];
$convId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$action = trim((string)($_POST['action'] ?? ''));

if ($convId <= 0 || $action === '') {
    header('Location: chat.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$convId, $userId]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conv) {
    header('Location: chat.php');
    exit;
}

$ownerId = (int)$conv['owner_id'];
$pgId = (int)$conv['pg_id'];

if ($action === 'ready_yes' || $action === 'ready_no') {
    $isReady = $action === 'ready_yes';
    chat_insert_message($pdo, [
        'conversation_id' => $convId,
        'sender_id' => $userId,
        'sender_role' => 'user',
        'recipient_role' => 'owner',
        'message_type' => 'reply',
        'action_key' => $isReady ? 'ready_yes' : 'ready_no',
        'message' => $isReady ? 'Yes, I am ready to take the room.' : 'Not yet. I need more time before booking.',
        'metadata' => ['pg_id' => $pgId],
    ]);
    notify_user($pdo, $ownerId, 'owner', 'Chat readiness reply', $isReady ? 'User is ready to take the room.' : 'User is not ready yet.', base_url('owner/chat.php?c=' . $convId));
    audit_log($pdo, 'chat_readiness_reply', 'conversation', $convId, 'ready=' . ($isReady ? 'yes' : 'no'));
}

header('Location: chat.php?c=' . $convId);
exit;
