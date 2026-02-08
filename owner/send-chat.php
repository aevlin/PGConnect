<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php'); exit;
}

ensure_chat_schema($pdo);
$ownerId = (int)$_SESSION['user_id'];
$convId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$message = trim($_POST['message'] ?? '');
if (!$convId || $message === '') { header('Location: chat.php'); exit; }

// verify conversation belongs to owner
$c = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND owner_id = ? LIMIT 1');
$c->execute([$convId, $ownerId]);
if (!$c->fetchColumn()) { header('Location: chat.php'); exit; }

$m = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, sender_role, recipient_role, message, is_read) VALUES (?, ?, ?, ?, ?, 0)');
$m->execute([$convId, $ownerId, 'owner', 'user', $message]);

header('Location: chat.php?c=' . $convId); exit;
