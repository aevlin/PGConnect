<?php
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../backend/login.php'); exit;
}

ensure_bookings_schema($pdo);
ensure_system_schema($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
if (!$id || !in_array($action, ['confirm','cancel','left'], true)) {
    header('Location: booking-request.php'); exit;
}

// Verify booking belongs to user
$stmt = $pdo->prepare('SELECT b.* FROM bookings b WHERE b.id = ? LIMIT 1');
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$b || (int)$b['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: booking-request.php'); exit;
}

// Status transitions
if ($action === 'confirm') {
    if (!in_array($b['status'], ['owner_approved','approved'], true)) {
        header('Location: booking-request.php'); exit;
    }
    $newStatus = 'payment_pending';
} elseif ($action === 'cancel') {
    // cancel allowed for requested or owner_approved
    if (!in_array($b['status'], ['requested','owner_approved','approved','payment_pending'], true)) {
        header('Location: booking-request.php'); exit;
    }
    $newStatus = 'user_rejected';
} else {
    if (!in_array($b['status'], ['paid'], true)) {
        header('Location: booking-request.php'); exit;
    }
    $newStatus = 'left';
}

if ($action === 'left') {
    $upd = $pdo->prepare('UPDATE bookings SET status = ?, moved_out_at = NOW(), user_action_at = NOW() WHERE id = ?');
    $upd->execute([$newStatus, $id]);
} else {
    $upd = $pdo->prepare('UPDATE bookings SET status = ?, user_action_at = NOW() WHERE id = ?');
    $upd->execute([$newStatus, $id]);
}

// log to admin alerts
@file_put_contents(__DIR__ . '/../backend/booking_notifications.log', date('Y-m-d H:i:s') . " USER_ACTION: booking_id={$id} status={$newStatus}\n", FILE_APPEND);
// notify owner
try {
    $own = $pdo->prepare('SELECT p.owner_id, p.pg_name FROM pg_listings p WHERE p.id = ? LIMIT 1');
    $own->execute([(int)$b['pg_id']]);
    $o = $own->fetch(PDO::FETCH_ASSOC);
    if ($o && !empty($o['owner_id'])) {
        notify_user($pdo, (int)$o['owner_id'], 'owner', 'Booking action by user', "Booking #{$id} is now {$newStatus}.", '/PGConnect/owner/owner-bookings.php');
    }
} catch (Throwable $e) {}
audit_log($pdo, 'user_booking_' . $action, 'booking', (int)$id, "status={$newStatus}");

if ($newStatus === 'payment_pending') {
    header('Location: payment.php?booking_id=' . (int)$id);
    exit;
}
if ($newStatus === 'left') {
    header('Location: pg-detail.php?id=' . (int)$b['pg_id'] . '&review_prompt=1');
    exit;
}
header('Location: booking-request.php'); exit;
