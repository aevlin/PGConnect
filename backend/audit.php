<?php
require_once __DIR__ . '/system_schema.php';

function audit_log(PDO $pdo, $action, $targetType = null, $targetId = null, $details = null) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_set_cookie_params(0, '/');
        session_start();
    }
    ensure_system_schema($pdo);
    $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $actorRole = $_SESSION['user_role'] ?? null;
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (actor_id, actor_role, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $actorRole, (string)$action, $targetType, $targetId, $details]);
    } catch (Throwable $e) {
        // avoid breaking primary flow
    }
}

