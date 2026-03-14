<?php
require_once __DIR__ . '/system_schema.php';

function notify_user(PDO $pdo, $userId, $userRole, $title, $message = '', $link = null) {
    ensure_system_schema($pdo);
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, user_role, title, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->execute([(int)$userId, (string)$userRole, (string)$title, (string)$message, $link]);
    } catch (Throwable $e) {
        // best-effort
    }
}

function unread_notifications_count(PDO $pdo, $userId, $userRole) {
    ensure_system_schema($pdo);
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND user_role = ? AND is_read = 0');
        $stmt->execute([(int)$userId, (string)$userRole]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

