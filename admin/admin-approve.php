<?php
// admin/admin-approve.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../backend/login.php');
    exit;
}
require_once '../backend/connect.php';

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
    }
}

header('Location: admin-all-pgs.php');
exit;
