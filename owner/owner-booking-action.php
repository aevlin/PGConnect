<?php
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php'); exit;
}

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$action = $_REQUEST['action'] ?? '';
$rescheduleAtRaw = trim($_POST['visit_datetime'] ?? '');
// debug
if (defined('DEV_MODE') && DEV_MODE) {
    @file_put_contents(__DIR__ . '/../backend/error.log', date('Y-m-d H:i:s') . " OWNER-ACTION: id={$id} action={$action} user=" . ($_SESSION['user_id'] ?? 'guest') . "\n", FILE_APPEND);
}
if (!$id || !in_array($action, ['approve','reject','visit_accept','visit_cancel','visit_reschedule'], true)) {
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
ensure_system_schema($pdo);
if (in_array($action, ['approve', 'reject'], true) && !in_array($b['status'], ['requested','owner_approved','owner_rejected','payment_pending'], true)) {
    header('Location: owner-bookings.php'); exit;
}

if ($action === 'approve' || $action === 'reject') {
    $newStatus = $action === 'approve' ? 'owner_approved' : 'owner_rejected';
    $upd = $pdo->prepare('UPDATE bookings SET status = ?, owner_action_at = NOW() WHERE id = ?');
    $upd->execute([$newStatus, $id]);

    $note = "Your booking request (id={$id}) has been {$newStatus}.";
    @file_put_contents(__DIR__ . '/../backend/booking_notifications.log', date('Y-m-d H:i:s') . " OWNER_ACTION: " . $note . "\n", FILE_APPEND);
    if (!empty($b['requester_email'])) {
        @mail($b['requester_email'], 'Booking update', $note, 'From: noreply@pgconnect.local');
    }
    notify_user($pdo, (int)$b['user_id'], 'user', 'Booking status updated', $note, base_url('user/booking-request.php'));
    audit_log($pdo, 'owner_booking_' . $action, 'booking', (int)$id, $note);
} elseif ($action === 'visit_accept') {
    $upd = $pdo->prepare("UPDATE bookings SET visit_requested = 1, visit_status = 'accepted', visit_note = CONCAT(COALESCE(visit_note,''), ' | Visit accepted'), owner_action_at = NOW() WHERE id = ?");
    $upd->execute([$id]);
    notify_user($pdo, (int)$b['user_id'], 'user', 'Visit request accepted', 'Owner accepted your visit appointment request.', base_url('user/booking-request.php'));
    audit_log($pdo, 'owner_visit_accept', 'booking', (int)$id, 'visit accepted');
} elseif ($action === 'visit_cancel') {
    $upd = $pdo->prepare("UPDATE bookings SET visit_requested = 0, visit_status = 'cancelled', visit_note = CONCAT(COALESCE(visit_note,''), ' | Visit cancelled by owner'), owner_action_at = NOW() WHERE id = ?");
    $upd->execute([$id]);
    notify_user($pdo, (int)$b['user_id'], 'user', 'Visit request cancelled', 'Owner cancelled the visit appointment request.', base_url('user/booking-request.php'));
    audit_log($pdo, 'owner_visit_cancel', 'booking', (int)$id, 'visit cancelled');
} elseif ($action === 'visit_reschedule') {
    $ts = strtotime($rescheduleAtRaw);
    if ($ts !== false) {
        $rescheduleAt = date('Y-m-d H:i:s', $ts);
        $upd = $pdo->prepare("UPDATE bookings SET visit_requested = 1, visit_status = 'rescheduled', visit_datetime = ?, visit_note = CONCAT(COALESCE(visit_note,''), ' | Rescheduled by owner'), owner_action_at = NOW() WHERE id = ?");
        $upd->execute([$rescheduleAt, $id]);
        notify_user($pdo, (int)$b['user_id'], 'user', 'Visit appointment rescheduled', 'Owner suggested a new visit time.', base_url('user/booking-request.php'));
        audit_log($pdo, 'owner_visit_reschedule', 'booking', (int)$id, 'visit_datetime=' . $rescheduleAt);
    }
}

header('Location: owner-bookings.php'); exit;
