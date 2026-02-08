<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth_required']);
    exit;
}

$pgId = isset($_POST['pg_id']) ? (int)$_POST['pg_id'] : 0;
if (!$pgId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pg_id_required']);
    exit;
}

if (!isset($_SESSION['compare_pgs'])) $_SESSION['compare_pgs'] = [];
$list = $_SESSION['compare_pgs'];

if (in_array($pgId, $list, true)) {
    $list = array_values(array_filter($list, fn($id) => (int)$id !== $pgId));
    $action = 'removed';
} else {
    $list[] = $pgId;
    $list = array_values(array_unique($list));
    $action = 'added';
}
$_SESSION['compare_pgs'] = $list;

echo json_encode(['ok' => true, 'action' => $action, 'count' => count($list)]);
