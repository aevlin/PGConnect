<?php
header('Content-Type: application/json');
require_once 'connect.php';
require_once __DIR__ . '/messages_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => true, 'count' => 0]);
    exit;
}

ensure_chat_schema($pdo);
$role = $_SESSION['user_role'] ?? 'user';

try {
    $count = 0;
    if ($role === 'owner') {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE c.owner_id = ?
              AND m.sender_role IN ("user","admin")
              AND (m.recipient_role = "owner" OR m.recipient_role IS NULL)
              AND m.is_read = 0
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $count = (int)$stmt->fetchColumn();
    } elseif ($role === 'user') {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE c.user_id = ?
              AND m.sender_role IN ("owner","admin")
              AND (m.recipient_role = "user" OR m.recipient_role IS NULL)
              AND m.is_read = 0
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $count = (int)$stmt->fetchColumn();
    } elseif ($role === 'admin') {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE c.admin_id = ?
              AND COALESCE(c.conversation_type, "tenant_owner") = "admin_owner"
              AND m.sender_role = "owner"
              AND COALESCE(m.is_read_admin, 0) = 0
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $count = (int)$stmt->fetchColumn();
    }
    echo json_encode(['ok' => true, 'count' => $count]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'count' => 0]);
}
