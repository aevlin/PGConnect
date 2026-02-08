<?php
$page_title = 'Booking Requests';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ' . BASE_URL . '/backend/login.php'); exit;
}

$ownerId = (int)$_SESSION['user_id'];
ensure_bookings_schema($pdo);
require_once '../includes/header.php';

// debug log page load
if (defined('DEV_MODE') && DEV_MODE) {
  @file_put_contents(__DIR__ . '/../backend/error.log', date('Y-m-d H:i:s') . " OWNER-BOOKINGS LOAD: owner={$ownerId} pg_id=" . ($_GET['pg_id'] ?? '') . "\n", FILE_APPEND);
}

// Fetch bookings for owner's PGs (optional filter by pg_id)
$bookings = [];
$queryError = '';
$pgFilter = isset($_GET['pg_id']) ? (int)$_GET['pg_id'] : 0;
try {
  if ($pgFilter > 0) {
    $stmt = $pdo->prepare('SELECT b.*, p.pg_name, u.name as requester_name, u.email as requester_email FROM bookings b JOIN pg_listings p ON p.id = b.pg_id JOIN users u ON u.id = b.user_id WHERE p.owner_id = ? AND p.id = ? ORDER BY b.created_at DESC');
    $stmt->execute([$ownerId, $pgFilter]);
  } else {
    $stmt = $pdo->prepare('SELECT b.*, p.pg_name, u.name as requester_name, u.email as requester_email FROM bookings b JOIN pg_listings p ON p.id = b.pg_id JOIN users u ON u.id = b.user_id WHERE p.owner_id = ? ORDER BY b.created_at DESC');
    $stmt->execute([$ownerId]);
  }
  $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $log = __DIR__ . '/../backend/error.log';
  @mkdir(dirname($log), 0755, true);
  @file_put_contents($log, date('Y-m-d H:i:s') . " OWNER-BOOKINGS ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
  $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Failed to load booking requests.';
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">Booking requests</h1>
      <p class="small text-muted">Requests from tenants for your listings</p>
    </div>
    <a href="owner-dashboard.php" class="btn btn-outline-secondary">← Back to dashboard</a>
  </div>

  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="alert alert-info">No booking requests yet.</div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($bookings as $b): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between">
            <div>
              <strong><?php echo htmlspecialchars($b['pg_name']); ?></strong>
              <div class="small text-muted">Requested by <?php echo htmlspecialchars($b['requester_name']); ?> (<?php echo htmlspecialchars($b['requester_email']); ?>) on <?php echo htmlspecialchars($b['created_at']); ?></div>
            </div>
            <div class="text-end">
              <div class="small">Status: <strong><?php echo htmlspecialchars($b['status']); ?></strong></div>
              <div class="mt-2">
                <?php if (in_array($b['status'], ['requested','owner_rejected','user_rejected'], true)): ?>
                  <a href="owner-booking-action.php?id=<?php echo $b['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                  <a href="owner-booking-action.php?id=<?php echo $b['id']; ?>&action=reject" class="btn btn-sm btn-outline-danger">Reject</a>
                <?php elseif (in_array($b['status'], ['owner_approved','approved'], true)): ?>
                  <span class="badge bg-success">Awaiting user confirmation</span>
                <?php else: ?>
                  <span class="badge bg-secondary">No action</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if (!empty($b['message'])): ?><div class="mt-2"><em><?php echo nl2br(htmlspecialchars($b['message'])); ?></em></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
