<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}

ensure_bookings_schema($pdo);
$ownerId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM bookings b
        JOIN pg_listings p ON p.id = b.pg_id
        WHERE p.owner_id = ?
          AND b.status = "requested"
    ');
    $stmt->execute([$ownerId]);
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['ok' => true, 'count' => $count]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'count' => 0]);
}

