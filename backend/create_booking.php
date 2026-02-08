<?php
// backend/create_booking.php
header('Content-Type: application/json');
require_once 'connect.php';
require_once 'config.php';
require_once __DIR__ . '/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
$contact_name = trim($_POST['contact_name'] ?? $_SESSION['user_name'] ?? '');
$contact_phone = trim($_POST['contact_phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($pgId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pg_id_required']);
    exit;
}

try {
    // Ensure bookings schema exists (safe for local dev)
    ensure_bookings_schema($pdo);

    // Insert booking
    $ins = $pdo->prepare('INSERT INTO bookings (user_id, pg_id, contact_name, contact_phone, message, status) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->execute([$userId, $pgId, $contact_name, $contact_phone, $message, 'requested']);
    $bookingId = $pdo->lastInsertId();

    // Notify owner: find owner email
    $stmt = $pdo->prepare('SELECT u.email, u.name FROM users u JOIN pg_listings p ON p.owner_id = u.id WHERE p.id = ? LIMIT 1');
    $stmt->execute([$pgId]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $note = "New booking request: booking_id={$bookingId}, pg_id={$pgId}, user_id={$userId}\nName: {$contact_name}\nPhone: {$contact_phone}\nMessage: {$message}\n";
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

    echo json_encode(['ok' => true, 'booking_id' => $bookingId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()]);
}

?>
