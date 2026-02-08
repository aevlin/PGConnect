<?php
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../backend/login.php'); exit;
}

ensure_bookings_schema($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
if (!$id || !in_array($action, ['confirm','cancel'], true)) {
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
    $newStatus = 'user_confirmed';
} else {
    // cancel allowed for requested or owner_approved
    if (!in_array($b['status'], ['requested','owner_approved','approved'], true)) {
        header('Location: booking-request.php'); exit;
    }
    $newStatus = 'user_rejected';
}

$upd = $pdo->prepare('UPDATE bookings SET status = ?, user_action_at = NOW() WHERE id = ?');
$upd->execute([$newStatus, $id]);

// update availability when confirmed
if ($newStatus === 'user_confirmed') {
    try {
        $stmt = $pdo->prepare('UPDATE pg_listings SET available_beds = GREATEST(available_beds - 1, 0) WHERE id = ?');
        $stmt->execute([(int)$b['pg_id']]);
        $st = $pdo->prepare('SELECT available_beds FROM pg_listings WHERE id = ?');
        $st->execute([(int)$b['pg_id']]);
        $avail = (int)$st->fetchColumn();
        $occ = $avail <= 0 ? 'full' : ($avail <= 2 ? 'filling_fast' : 'available');
        $pdo->prepare('UPDATE pg_listings SET occupancy_status = ? WHERE id = ?')->execute([$occ, (int)$b['pg_id']]);
    } catch (Throwable $e) {}
}

// log to admin alerts
@file_put_contents(__DIR__ . '/../backend/booking_notifications.log', date('Y-m-d H:i:s') . " USER_ACTION: booking_id={$id} status={$newStatus}\n", FILE_APPEND);

header('Location: booking-request.php'); exit;
