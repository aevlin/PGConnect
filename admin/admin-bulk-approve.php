<?php
// admin/admin-bulk-approve.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../backend/login.php');
    exit;
}
require_once '../backend/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['pg_ids'] ?? [];
    $action = $_POST['bulk_action'] ?? '';
    if (!is_array($ids) || empty($ids)) {
        header('Location: admin-all-pgs.php');
        exit;
    }

    $allowed = ['approve','reject'];
    if (!in_array($action, $allowed, true)) {
        header('Location: admin-all-pgs.php');
        exit;
    }

    $status = $action === 'approve' ? 'approved' : 'pending';

    // Prepare statement
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE pg_listings SET status = ? WHERE id IN ($in)");
    $params = array_merge([$status], array_map('intval', $ids));
    $stmt->execute($params);
}

header('Location: admin-all-pgs.php');
exit;
