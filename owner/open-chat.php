<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/auth.php';
require_role('owner');

ensure_chat_schema($pdo);
$ownerId = (int)$_SESSION['user_id'];
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    header('Location: owner-bookings.php'); exit;
}

$stmt = $pdo->prepare('SELECT b.user_id, b.pg_id, p.owner_id FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE b.id = ? LIMIT 1');
$stmt->execute([$bookingId]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$b || (int)$b['owner_id'] !== $ownerId) {
    header('Location: owner-bookings.php'); exit;
}

$c = $pdo->prepare("SELECT id FROM conversations WHERE user_id = ? AND owner_id = ? AND pg_id = ? AND COALESCE(conversation_type, 'tenant_owner') = 'tenant_owner' LIMIT 1");
$c->execute([(int)$b['user_id'], $ownerId, (int)$b['pg_id']]);
$convId = (int)$c->fetchColumn();
if ($convId <= 0) {
    $ins = $pdo->prepare("INSERT INTO conversations (user_id, owner_id, pg_id, conversation_type) VALUES (?, ?, ?, 'tenant_owner')");
    $ins->execute([(int)$b['user_id'], $ownerId, (int)$b['pg_id']]);
    $convId = (int)$pdo->lastInsertId();
}

header('Location: chat.php?c=' . $convId); exit;
