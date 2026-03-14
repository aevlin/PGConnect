<?php
$page_title = 'Booking Requests';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
require_once '../backend/auth.php';
require_role('owner');

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

  <?php
    $visitRows = array_values(array_filter($bookings, function($row){
      return !empty($row['visit_requested']);
    }));
  ?>
  <?php if (!empty($visitRows)): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6 mb-2">Visit Appointments</h2>
        <?php foreach ($visitRows as $vr): ?>
          <div class="border rounded p-2 mb-2">
            <div class="fw-semibold"><?php echo htmlspecialchars($vr['pg_name']); ?> - <?php echo htmlspecialchars($vr['requester_name']); ?></div>
            <div class="small text-muted">Preferred: <?php echo htmlspecialchars($vr['visit_datetime'] ?: 'Not set'); ?></div>
            <div class="d-flex gap-2 mt-2 flex-wrap">
              <a href="owner-booking-action.php?id=<?php echo (int)$vr['id']; ?>&action=visit_accept" class="btn btn-sm btn-success">Accept visit</a>
              <a href="owner-booking-action.php?id=<?php echo (int)$vr['id']; ?>&action=visit_cancel" class="btn btn-sm btn-outline-danger">Cancel visit</a>
              <form action="owner-booking-action.php" method="POST" class="d-flex gap-2">
                <input type="hidden" name="id" value="<?php echo (int)$vr['id']; ?>">
                <input type="hidden" name="action" value="visit_reschedule">
                <input type="datetime-local" name="visit_datetime" class="form-control form-control-sm" required>
                <button class="btn btn-sm btn-outline-primary">Reschedule</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
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
              <div class="small text-muted">Move-in date: <?php echo htmlspecialchars($b['move_in_date'] ?: '-'); ?><?php if (!empty($b['contact_phone'])): ?> · Phone: <?php echo htmlspecialchars($b['contact_phone']); ?><?php endif; ?></div>
              <?php if (!empty($b['visit_requested'])): ?>
                <div class="small text-primary">Visit appointment requested: <?php echo htmlspecialchars($b['visit_datetime'] ?: 'Time not set'); ?><?php if (!empty($b['visit_note'])): ?> · <?php echo htmlspecialchars($b['visit_note']); ?><?php endif; ?></div>
                <div class="mt-2 d-flex gap-2 flex-wrap">
                  <a href="owner-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=visit_accept" class="btn btn-sm btn-outline-success">Accept visit</a>
                  <a href="owner-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=visit_cancel" class="btn btn-sm btn-outline-danger">Cancel visit</a>
                </div>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <div class="small">Status: <strong><?php echo htmlspecialchars($b['status']); ?></strong></div>
              <div class="mt-2">
                <a href="open-chat.php?booking_id=<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-outline-primary">Chat User</a>
                <?php if (in_array($b['status'], ['requested'], true)): ?>
                  <a href="owner-booking-action.php?id=<?php echo $b['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                  <a href="owner-booking-action.php?id=<?php echo $b['id']; ?>&action=reject" class="btn btn-sm btn-outline-danger">Reject</a>
                <?php elseif (in_array($b['status'], ['owner_approved','approved'], true)): ?>
                  <span class="badge bg-success">Awaiting user agreement/payment</span>
                <?php elseif ($b['status'] === 'payment_pending'): ?>
                  <span class="badge bg-warning text-dark">User agreed, awaiting payment</span>
                <?php elseif ($b['status'] === 'paid'): ?>
                  <span class="badge bg-primary">Payment completed</span>
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
