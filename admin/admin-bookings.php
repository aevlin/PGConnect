<?php
session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';

ensure_bookings_schema($pdo);

$bookings = [];
$queryError = '';
try {
    $stmt = $pdo->query('SELECT b.*, p.pg_name, p.city, u.name AS user_name, o.name AS owner_name
                         FROM bookings b
                         JOIN pg_listings p ON p.id = b.pg_id
                         JOIN users u ON u.id = b.user_id
                         JOIN users o ON o.id = p.owner_id
                         ORDER BY b.created_at DESC');
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Failed to load booking requests.';
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h5 mb-0">Booking activity</h1>
      <p class="small text-muted mb-0">All booking requests and their status</p>
    </div>
    <a href="admin-dashboard.php" class="btn btn-outline-secondary">← Back to dashboard</a>
  </div>

  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="alert alert-info">No booking requests yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle bg-white shadow-sm">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>PG</th>
            <th>User</th>
            <th>Owner</th>
            <th>Status</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td><?php echo (int)$b['id']; ?></td>
              <td><?php echo htmlspecialchars($b['pg_name']); ?> <span class="text-muted small">· <?php echo htmlspecialchars($b['city']); ?></span></td>
              <td><?php echo htmlspecialchars($b['user_name']); ?></td>
              <td><?php echo htmlspecialchars($b['owner_name']); ?></td>
              <td><span class="badge bg-secondary"><?php echo htmlspecialchars($b['status']); ?></span></td>
              <td><?php echo htmlspecialchars($b['created_at']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
