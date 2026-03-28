<?php
require_once '../backend/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

require_once '../backend/connect.php';

$ownerId = (int)$_SESSION['user_id'];
$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
$action = trim((string)($_POST['action'] ?? ''));
$q = trim((string)($_POST['q'] ?? ''));

if ($pgId > 0) {
    try {
        if ($action === 'pause') {
            $stmt = $pdo->prepare("UPDATE pg_listings SET status = 'paused' WHERE id = ? AND owner_id = ?");
            $stmt->execute([$pgId, $ownerId]);
        } elseif ($action === 'resume') {
            $stmt = $pdo->prepare("UPDATE pg_listings SET status = 'approved' WHERE id = ? AND owner_id = ?");
            $stmt->execute([$pgId, $ownerId]);
        } elseif ($action === 'mark_full') {
            $stmt = $pdo->prepare("UPDATE pg_listings SET occupancy_status = 'full', available_beds = 0 WHERE id = ? AND owner_id = ?");
            $stmt->execute([$pgId, $ownerId]);
        } elseif ($action === 'beds') {
            $availableBeds = max(0, (int)($_POST['available_beds'] ?? 0));
            $capacityStmt = $pdo->prepare("SELECT capacity FROM pg_listings WHERE id = ? AND owner_id = ? LIMIT 1");
            $capacityStmt->execute([$pgId, $ownerId]);
            $capacity = (int)$capacityStmt->fetchColumn();
            if ($capacity > 0) {
                $availableBeds = min($availableBeds, $capacity);
                $occupancyStatus = 'available';
                if ($availableBeds <= 0) {
                    $occupancyStatus = 'full';
                } elseif ($availableBeds <= max(1, (int)floor($capacity / 3))) {
                    $occupancyStatus = 'filling_fast';
                }
                $stmt = $pdo->prepare("UPDATE pg_listings SET available_beds = ?, occupancy_status = ? WHERE id = ? AND owner_id = ?");
                $stmt->execute([$availableBeds, $occupancyStatus, $pgId, $ownerId]);
            }
        }
    } catch (Throwable $e) {
    }
}

$redirect = 'owner-dashboard.php';
if ($q !== '') {
    $redirect .= '?q=' . urlencode($q);
}
header('Location: ' . $redirect);
exit;
