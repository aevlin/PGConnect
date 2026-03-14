<?php
require_once '../backend/auth.php';
require_role('admin');
require_once '../backend/connect.php';
require_once '../backend/user_schema.php';
require_once '../backend/audit.php';

ensure_user_profile_schema($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
if (!$id || !in_array($action, ['approve','reject'], true)) {
    header('Location: admin-owners.php'); exit;
}

$status = $action === 'approve' ? 'verified' : 'rejected';
$stmt = $pdo->prepare("UPDATE users SET owner_verification_status = ? WHERE id = ? AND role = 'owner'");
$stmt->execute([$status, $id]);
audit_log($pdo, 'admin_owner_' . $action, 'user', $id, 'owner_verification_status=' . $status);

header('Location: admin-owners.php'); exit;
