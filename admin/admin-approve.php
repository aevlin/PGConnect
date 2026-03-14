<?php
// admin/admin-approve.php
require_once '../backend/auth.php';
require_role('admin');
require_once '../backend/connect.php';
require_once '../backend/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pgId   = (int)($_POST['pg_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($pgId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'approved' : 'pending';

        $stmt = $pdo->prepare('UPDATE pg_listings SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => $newStatus,
            ':id'     => $pgId
        ]);
        audit_log($pdo, 'admin_pg_' . $action, 'pg_listing', $pgId, 'status=' . $newStatus);
    }
}

header('Location: admin-all-pgs.php');
exit;
