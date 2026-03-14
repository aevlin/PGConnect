<?php
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
require_once '../backend/system_schema.php';
require_once '../backend/auth.php';
require_role('user');
require_once '../includes/header.php';

ensure_bookings_schema($pdo);
ensure_system_schema($pdo);

$userId = (int)$_SESSION['user_id'];
$paymentSuccessMessage = $_SESSION['payment_success_message'] ?? '';
if ($paymentSuccessMessage !== '') {
    unset($_SESSION['payment_success_message']);
}
$bookings = [];
$currentStays = [];
$upcomingStays = [];
$queryError = '';
try {
    $stmt = $pdo->prepare('SELECT b.*, p.pg_name, p.city, p.address, u.name AS owner_name
                           FROM bookings b
                           JOIN pg_listings p ON p.id = b.pg_id
                           JOIN users u ON u.id = p.owner_id
                           WHERE b.user_id = ?
                           ORDER BY b.created_at DESC');
    $stmt->execute([$userId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $today = date('Y-m-d');
    foreach ($bookings as $b) {
        if (($b['status'] ?? '') === 'paid' && empty($b['moved_out_at'])) {
            if (!empty($b['move_in_date']) && $b['move_in_date'] > $today) {
                $upcomingStays[] = $b;
            } else {
                $currentStays[] = $b;
            }
        }
    }
} catch (Throwable $e) {
    $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Failed to load booking requests.';
}
?>

<div class="container py-5">
  <h1 class="h4 mb-3">Booking Requests</h1>
  <?php if ($paymentSuccessMessage !== ''): ?>
    <div class="alert alert-success" id="paymentSuccessInline"><?php echo htmlspecialchars($paymentSuccessMessage); ?></div>
  <?php endif; ?>
  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <?php if (!empty($currentStays)): ?>
    <div class="card border-success mb-4">
      <div class="card-body">
        <h2 class="h6 text-success mb-2">You are currently living here</h2>
        <?php foreach ($currentStays as $s): ?>
          <div class="d-flex justify-content-between align-items-start border-bottom py-2">
            <div>
              <div class="fw-semibold"><?php echo htmlspecialchars($s['pg_name']); ?></div>
              <div class="small text-muted"><?php echo htmlspecialchars($s['address'] ?? ''); ?>, <?php echo htmlspecialchars($s['city'] ?? ''); ?></div>
              <div class="small text-muted">Since: <?php echo htmlspecialchars($s['move_in_date'] ?: '-'); ?></div>
            </div>
            <div class="text-end">
              <a class="btn btn-sm btn-outline-danger" href="user-booking-action.php?id=<?php echo (int)$s['id']; ?>&action=left">I Left This PG</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($upcomingStays)): ?>
    <div class="alert alert-info mb-4">
      <strong>Upcoming move-in:</strong>
      <?php foreach ($upcomingStays as $u): ?>
        <div class="small"><?php echo htmlspecialchars($u['pg_name']); ?> on <?php echo htmlspecialchars($u['move_in_date']); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($bookings)): ?>
    <div class="alert alert-info">No booking requests yet. When you request a booking, it will appear here.</div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($bookings as $b): ?>
        <div class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <strong><?php echo htmlspecialchars($b['pg_name']); ?></strong>
              <div class="small text-muted"><?php echo htmlspecialchars($b['address'] ?? ''); ?>, <?php echo htmlspecialchars($b['city'] ?? ''); ?></div>
              <div class="small text-muted">Owner: <?php echo htmlspecialchars($b['owner_name']); ?></div>
              <div class="small text-muted">Move-in date: <?php echo htmlspecialchars($b['move_in_date'] ?: '-'); ?></div>
              <?php if (!empty($b['visit_requested'])): ?>
                <div class="small text-primary">Visit requested: <?php echo htmlspecialchars($b['visit_datetime'] ?: 'Time not set'); ?><?php if (!empty($b['visit_note'])): ?> · <?php echo htmlspecialchars($b['visit_note']); ?><?php endif; ?></div>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <div class="small">Status: <strong><?php echo htmlspecialchars($b['status']); ?></strong></div>
              <div class="small text-muted">Payment: <?php echo htmlspecialchars($b['payment_status'] ?? 'unpaid'); ?></div>
              <div class="small text-muted">Timeline:</div>
              <div class="small">
                <?php
                  $status = (string)($b['status'] ?? '');
                  $steps = [
                    'Requested' => in_array($status, ['requested','owner_approved','payment_pending','paid','left'], true),
                    'Owner Approved' => in_array($status, ['owner_approved','payment_pending','paid','left'], true),
                    'User Agreed' => in_array($status, ['payment_pending','paid','left'], true),
                    'Paid' => in_array($status, ['paid','left'], true),
                    'Living/Completed' => in_array($status, ['paid','left'], true)
                  ];
                  foreach ($steps as $name => $done) {
                    echo $done ? '<span class="badge bg-success-subtle text-success border me-1 mb-1">' . htmlspecialchars($name) . '</span>' : '<span class="badge bg-light text-muted border me-1 mb-1">' . htmlspecialchars($name) . '</span>';
                  }
                ?>
              </div>
              <div class="mt-2">
                <?php if ($b['status'] === 'owner_approved'): ?>
                  <a class="btn btn-sm btn-success" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=confirm">Agree & Pay</a>
                  <a class="btn btn-sm btn-outline-danger" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=cancel">Cancel</a>
                <?php elseif ($b['status'] === 'payment_pending'): ?>
                  <a class="btn btn-sm btn-primary" href="payment.php?booking_id=<?php echo (int)$b['id']; ?>">Complete payment</a>
                  <a class="btn btn-sm btn-outline-danger" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=cancel">Cancel</a>
                <?php elseif ($b['status'] === 'paid'): ?>
                  <span class="badge bg-success">Booking confirmed</span>
                  <?php if (empty($b['moved_out_at'])): ?>
                    <a class="btn btn-sm btn-outline-danger ms-1" href="leave-pg.php?booking_id=<?php echo (int)$b['id']; ?>">I Left This PG</a>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-outline-secondary ms-1" href="receipt.php?booking_id=<?php echo (int)$b['id']; ?>">Receipt</a>
                <?php elseif ($b['status'] === 'left'): ?>
                  <a class="btn btn-sm btn-outline-primary" href="pg-detail.php?id=<?php echo (int)$b['pg_id']; ?>&review_prompt=1">Add review</a>
                <?php elseif ($b['status'] === 'requested'): ?>
                  <a class="btn btn-sm btn-outline-danger" href="user-booking-action.php?id=<?php echo (int)$b['id']; ?>&action=cancel">Cancel</a>
                <?php else: ?>
                  <span class="badge bg-secondary">No action</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if (!empty($b['message'])): ?>
            <div class="mt-2"><em><?php echo nl2br(htmlspecialchars($b['message'])); ?></em></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

<?php if ($paymentSuccessMessage !== ''): ?>
<div class="modal fade" id="paymentSuccessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment Successful</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0"><?php echo htmlspecialchars($paymentSuccessMessage); ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue</button>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  const modalEl = document.getElementById('paymentSuccessModal');
  if (!modalEl || typeof bootstrap === 'undefined') return;
  const m = new bootstrap.Modal(modalEl);
  m.show();
})();
</script>
<?php endif; ?>
