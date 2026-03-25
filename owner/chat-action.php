<?php
require_once '../backend/connect.php';
require_once '../backend/messages_schema.php';
require_once '../backend/booking_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/notify.php';
require_once '../backend/audit.php';
require_once '../backend/auth.php';
require_role('owner');

ensure_chat_schema($pdo);
ensure_bookings_schema($pdo);
ensure_system_schema($pdo);

$ownerId = (int)$_SESSION['user_id'];
$convId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
$action = trim((string)($_POST['action'] ?? ''));

if ($convId <= 0 || $action === '') {
    header('Location: chat.php');
    exit;
}

$stmt = $pdo->prepare('SELECT c.*, p.pg_name, p.monthly_rent FROM conversations c LEFT JOIN pg_listings p ON p.id = c.pg_id WHERE c.id = ? AND c.owner_id = ? LIMIT 1');
$stmt->execute([$convId, $ownerId]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conv) {
    header('Location: chat.php');
    exit;
}

if (($conv['conversation_type'] ?? 'tenant_owner') !== 'tenant_owner') {
    $_SESSION['chat_flash_error'] = 'Booking actions are only available in private tenant chats.';
    header('Location: chat.php?c=' . $convId);
    exit;
}

$userId = (int)$conv['user_id'];
$pgId = (int)$conv['pg_id'];

if ($action === 'ask_ready') {
    chat_insert_message($pdo, [
        'conversation_id' => $convId,
        'sender_id' => $ownerId,
        'sender_role' => 'owner',
        'recipient_role' => 'user',
        'message_type' => 'prompt',
        'action_key' => 'room_readiness',
        'message' => 'Are you ready to take this room? You can reply from this chat.',
        'metadata' => ['pg_id' => $pgId],
    ]);
    notify_user($pdo, $userId, 'user', 'Owner asked about room readiness', 'Reply in chat if you are ready to take the room.', '/PGConnect/user/chat.php?c=' . $convId);
    audit_log($pdo, 'chat_readiness_prompt_sent', 'conversation', $convId, 'owner_id=' . $ownerId);
    header('Location: chat.php?c=' . $convId);
    exit;
}

if ($action === 'create_booking') {
    $contactPhone = trim((string)($_POST['contact_phone'] ?? ''));
    $moveInDate = trim((string)($_POST['move_in_date'] ?? ''));
    $ownerNote = trim((string)($_POST['owner_note'] ?? ''));
    $contactName = trim((string)($_POST['contact_name'] ?? ''));
    if ($contactName === '') {
        $u = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $u->execute([$userId]);
        $contactName = (string)($u->fetchColumn() ?: 'User');
    }
    if ($moveInDate === '') {
        $_SESSION['chat_flash_error'] = 'Move-in date is required to create booking from chat.';
        header('Location: chat.php?c=' . $convId);
        exit;
    }

    $dup = $pdo->prepare("
        SELECT id FROM bookings
        WHERE user_id = ? AND pg_id = ?
          AND (
            status IN ('requested','owner_approved','approved','user_confirmed','payment_pending')
            OR (status = 'paid' AND moved_out_at IS NULL)
          )
        LIMIT 1
    ");
    $dup->execute([$userId, $pgId]);
    if ($dup->fetchColumn()) {
        $_SESSION['chat_flash_error'] = 'This user already has an active booking or is staying in this PG.';
        header('Location: chat.php?c=' . $convId);
        exit;
    }

    $amount = (float)($conv['monthly_rent'] ?? 0);
    $ins = $pdo->prepare('
        INSERT INTO bookings (
            user_id, pg_id, contact_name, contact_phone, move_in_date,
            message, payment_amount, payment_status, status, owner_action_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $ins->execute([
        $userId,
        $pgId,
        $contactName,
        $contactPhone !== '' ? $contactPhone : null,
        $moveInDate,
        $ownerNote !== '' ? $ownerNote : 'Booking created by owner from chat.',
        $amount,
        'unpaid',
        'owner_approved',
    ]);
    $bookingId = (int)$pdo->lastInsertId();

    chat_insert_message($pdo, [
        'conversation_id' => $convId,
        'sender_id' => $ownerId,
        'sender_role' => 'owner',
        'recipient_role' => 'user',
        'message_type' => 'booking_offer',
        'action_key' => 'booking_created',
        'message' => 'I created a booking for you from this chat. Please open your bookings page to confirm and complete payment.',
        'metadata' => ['booking_id' => $bookingId, 'pg_id' => $pgId, 'move_in_date' => $moveInDate],
    ]);
    notify_user($pdo, $userId, 'user', 'Booking created from chat', 'Owner created a booking for you. Review and complete payment.', '/PGConnect/user/booking-request.php');
    audit_log($pdo, 'chat_booking_created', 'booking', $bookingId, 'conversation_id=' . $convId);
    $_SESSION['chat_flash_success'] = 'Booking created from chat.';
    header('Location: chat.php?c=' . $convId);
    exit;
}

header('Location: chat.php?c=' . $convId);
exit;
