<?php
// admin/admin-bulk-approve.php
require_once '../backend/auth.php';
require_role('admin');
require_once '../backend/connect.php';
require_once '../backend/audit.php';

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
    audit_log($pdo, 'admin_pg_bulk_' . $action, 'pg_listing', null, 'count=' . count($ids) . '; status=' . $status);
}

header('Location: admin-all-pgs.php');
exit;
