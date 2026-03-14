<?php
// backend/create_booking.php
header('Content-Type: application/json');
require_once 'connect.php';
require_once 'config.php';
require_once __DIR__ . '/booking_schema.php';
require_once __DIR__ . '/system_schema.php';
require_once __DIR__ . '/feature_schema.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/audit.php';
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden', 'message' => 'Only tenant user accounts can create bookings.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
$contact_name = trim($_POST['contact_name'] ?? $_SESSION['user_name'] ?? '');
$contact_phone = trim($_POST['contact_phone'] ?? '');
$moveInDate = trim($_POST['move_in_date'] ?? '');
$visitRequested = isset($_POST['visit_requested']) && $_POST['visit_requested'] === '1' ? 1 : 0;
$visitDatetimeRaw = trim($_POST['visit_datetime'] ?? '');
$visitNote = trim($_POST['visit_note'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($pgId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pg_id_required', 'message' => 'PG id is required.']);
    exit;
}
if ($moveInDate === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'move_in_date_required', 'message' => 'Join date is required.']);
    exit;
}
if ($visitRequested && $visitDatetimeRaw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'visit_datetime_required', 'message' => 'Visit appointment date/time is required.']);
    exit;
}

$visitDatetime = null;
if ($visitDatetimeRaw !== '') {
    $ts = strtotime($visitDatetimeRaw);
    if ($ts !== false) $visitDatetime = date('Y-m-d H:i:s', $ts);
}
$moveInTs = strtotime($moveInDate);
if ($moveInTs === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_move_in_date', 'message' => 'Join date is invalid. Please select a valid date.']);
    exit;
}
$moveInDate = date('Y-m-d', $moveInTs);
if ($visitRequested && $visitDatetime === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_visit_datetime', 'message' => 'Visit appointment date/time is invalid.']);
    exit;
}

try {
    // Ensure bookings schema exists (safe for local dev)
    ensure_bookings_schema($pdo);
    ensure_system_schema($pdo);
    ensure_feature_schema($pdo);

    // Prevent duplicate active booking for same PG+user
    // Allow booking again only after user has left the PG.
    $dup = $pdo->prepare("
        SELECT status, moved_out_at
        FROM bookings
        WHERE user_id = ? AND pg_id = ?
          AND (
            status IN ('requested','owner_approved','approved','user_confirmed','payment_pending')
            OR (status = 'paid' AND moved_out_at IS NULL)
          )
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $dup->execute([$userId, $pgId]);
    $existing = $dup->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $dupStatus = (string)($existing['status'] ?? '');
        $message = 'You already have an active booking/request for this PG.';
        if ($dupStatus === 'paid') {
            $message = 'You are already staying in this PG.';
        } elseif (in_array($dupStatus, ['owner_approved', 'approved', 'user_confirmed', 'payment_pending'], true)) {
            $message = 'You already booked this PG. Please complete or manage the current booking.';
        }
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'duplicate_active_booking', 'message' => $message]);
        exit;
    }

    $priceStmt = $pdo->prepare('SELECT monthly_rent FROM pg_listings WHERE id = ? LIMIT 1');
    $priceStmt->execute([$pgId]);
    $paymentAmount = (float)$priceStmt->fetchColumn();
    if ($paymentAmount <= 0) $paymentAmount = 0;

    // Blocked-date validation
    $blk = $pdo->prepare('SELECT id FROM availability_blocks WHERE pg_id = ? AND block_date = ? LIMIT 1');
    $blk->execute([$pgId, $moveInDate]);
    if ($blk->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'date_blocked', 'message' => 'Selected join date is unavailable. Please choose another date.']);
        exit;
    }

    // Insert booking
    $ins = $pdo->prepare('INSERT INTO bookings (user_id, pg_id, contact_name, contact_phone, move_in_date, visit_requested, visit_datetime, visit_note, message, payment_amount, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([$userId, $pgId, $contact_name, $contact_phone, $moveInDate, $visitRequested, $visitDatetime, $visitNote, $message, $paymentAmount, 'unpaid', 'requested']);
    $bookingId = $pdo->lastInsertId();

    // Notify owner: find owner email
    $stmt = $pdo->prepare('SELECT u.email, u.name FROM users u JOIN pg_listings p ON p.owner_id = u.id WHERE p.id = ? LIMIT 1');
    $stmt->execute([$pgId]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $visitLine = $visitRequested ? ("Visit requested at: " . ($visitDatetime ?: $visitDatetimeRaw) . "\nVisit note: {$visitNote}\n") : "";
    $note = "New booking request: booking_id={$bookingId}, pg_id={$pgId}, user_id={$userId}\nName: {$contact_name}\nPhone: {$contact_phone}\nMove-in date: {$moveInDate}\n{$visitLine}Message: {$message}\n";
    // log
    @file_put_contents(__DIR__ . '/booking_notifications.log', date('Y-m-d H:i:s') . " USER_REQUEST: " . $note . "\n", FILE_APPEND);
    // attempt email to owner if available
    if ($owner && !empty($owner['email'])) {
        $subject = "New booking request for your PG (ID: {$pgId})";
        $body = "Hello {$owner['name']},\n\nYou have a new booking request for your PG (ID: {$pgId}).\n\nDetails:\n" . $note;
        @mail($owner['email'], $subject, $body, 'From: noreply@pgconnect.local');
    }

    // Notify admin (best-effort)
    if (defined('BOOKING_ADMIN_NOTIFY') && BOOKING_ADMIN_NOTIFY) {
        $subject = "PGConnect: new booking request";
        $body = "New booking request logged.\n\n" . $note;
        send_admin_alert($subject, $body);
        $pdo->prepare('UPDATE bookings SET admin_notified_at = NOW() WHERE id = ?')->execute([$bookingId]);
    }

    // In-app notifications
    if ($owner && !empty($owner['email'])) {
        $ownerIdStmt = $pdo->prepare('SELECT owner_id FROM pg_listings WHERE id = ? LIMIT 1');
        $ownerIdStmt->execute([$pgId]);
        $ownerId = (int)$ownerIdStmt->fetchColumn();
        if ($ownerId > 0) {
            notify_user($pdo, $ownerId, 'owner', 'New booking request', 'A user requested booking for one of your PGs.', BASE_URL . '/owner/owner-bookings.php');
        }
    }
    // notify admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $aid) {
        notify_user($pdo, (int)$aid, 'admin', 'New booking request', "Booking #{$bookingId} requires monitoring.", BASE_URL . '/admin/admin-bookings.php');
    }
    audit_log($pdo, 'booking_created', 'booking', (int)$bookingId, "pg_id={$pgId}; user_id={$userId}");

    echo json_encode(['ok' => true, 'booking_id' => $bookingId]);
} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " CREATE_BOOKING ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => 'Server error while saving booking request.']);
}

?>
