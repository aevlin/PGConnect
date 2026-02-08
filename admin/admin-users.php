<?php
require_once '../includes/header.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../backend/login.php'); exit;
}
?>

<div class="container py-5">
  <h1 class="h4 mb-3">Manage Users</h1>
  <div class="alert alert-info">User management will be added here. (Placeholder)</div>
</div>

<?php require_once '../includes/footer.php'; ?>