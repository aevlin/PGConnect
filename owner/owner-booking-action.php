<?php
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php'); exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
// debug
if (defined('DEV_MODE') && DEV_MODE) {
    @file_put_contents(__DIR__ . '/../backend/error.log', date('Y-m-d H:i:s') . " OWNER-ACTION: id={$id} action={$action} user=" . ($_SESSION['user_id'] ?? 'guest') . "\n", FILE_APPEND);
}
if (!$id || !in_array($action, ['approve','reject'], true)) {
    header('Location: owner-bookings.php'); exit;
}

// verify booking belongs to this owner
$stmt = $pdo->prepare('SELECT b.*, p.owner_id, u.email as requester_email, u.name as requester_name FROM bookings b JOIN pg_listings p ON p.id = b.pg_id JOIN users u ON u.id = b.user_id WHERE b.id = ? LIMIT 1');
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$b || (int)$b['owner_id'] !== (int)$_SESSION['user_id']) {
    header('Location: owner-bookings.php'); exit;
}

ensure_bookings_schema($pdo);
$newStatus = $action === 'approve' ? 'owner_approved' : 'owner_rejected';
$upd = $pdo->prepare('UPDATE bookings SET status = ?, owner_action_at = NOW() WHERE id = ?');
$upd->execute([$newStatus, $id]);

// notify tenant (best-effort)
$note = "Your booking request (id={$id}) has been {$newStatus}. Please confirm to complete the booking.";
@file_put_contents(__DIR__ . '/../backend/booking_notifications.log', date('Y-m-d H:i:s') . " OWNER_ACTION: " . $note . "\n", FILE_APPEND);
if (!empty($b['requester_email'])) {
    @mail($b['requester_email'], 'Booking update', $note, 'From: noreply@pgconnect.local');
}

header('Location: owner-bookings.php'); exit;
