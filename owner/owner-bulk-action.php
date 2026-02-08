<?php
// owner/owner-bulk-action.php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php');
    exit;
}
require_once '../backend/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['pg_ids'] ?? [];
    $action = $_POST['action'] ?? '';
    if (!is_array($ids) || empty($ids)) {
        header('Location: owner-pg-list.php');
        exit;
    }

    $allowed = ['delete','mark_draft'];
    if (!in_array($action, $allowed, true)) {
        header('Location: owner-pg-list.php');
        exit;
    }

    if ($action === 'delete') {
        // delete rows and let FK cascade remove images if configured
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM pg_listings WHERE id IN ($in) AND owner_id = ?");
        $params = array_map('intval', $ids);
        $params[] = (int)$_SESSION['user_id'];
        $stmt->execute($params);
    } elseif ($action === 'mark_draft') {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE pg_listings SET status = 'pending' WHERE id IN ($in) AND owner_id = ?");
        $params = array_map('intval', $ids);
        $params[] = (int)$_SESSION['user_id'];
        $stmt->execute($params);
    }
}

header('Location: owner-pg-list.php');
exit;
