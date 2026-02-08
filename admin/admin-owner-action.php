<?php
session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

require_once '../backend/connect.php';
require_once '../backend/user_schema.php';

ensure_user_profile_schema($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';
if (!$id || !in_array($action, ['approve','reject'], true)) {
    header('Location: admin-owners.php'); exit;
}

$status = $action === 'approve' ? 'verified' : 'rejected';
$stmt = $pdo->prepare("UPDATE users SET owner_verification_status = ? WHERE id = ? AND role = 'owner'");
$stmt->execute([$status, $id]);

header('Location: admin-owners.php'); exit;
