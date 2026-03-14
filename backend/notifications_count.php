<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notify.php';

ensure_session_started();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}

$count = unread_notifications_count($pdo, (int)$_SESSION['user_id'], (string)($_SESSION['user_role'] ?? 'user'));
echo json_encode(['ok' => true, 'count' => $count]);

