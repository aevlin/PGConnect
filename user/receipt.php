<?php
require_once '../backend/connect.php';
require_once '../backend/auth.php';
require_once '../backend/booking_schema.php';

require_role('user');
ensure_bookings_schema($pdo);
require_once '../includes/header.php';

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    header('Location: booking-request.php'); exit;
}

$stmt = $pdo->prepare('SELECT b.*, p.pg_name, p.address, p.city, u.name AS user_name
                       FROM bookings b
                       JOIN pg_listings p ON p.id = b.pg_id
                       JOIN users u ON u.id = b.user_id
                       WHERE b.id = ? AND b.user_id = ? LIMIT 1');
$stmt->execute([$bookingId, (int)$_SESSION['user_id']]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) {
    header('Location: booking-request.php'); exit;
}
?>
<div class="container py-5">
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <h1 class="h4 mb-1">Payment Receipt</h1>
          <div class="small text-muted">Booking #<?php echo (int)$r['id']; ?></div>
        </div>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print</button>
      </div>
      <div class="row g-3">
        <div class="col-md-6"><strong>User:</strong> <?php echo htmlspecialchars($r['user_name']); ?></div>
        <div class="col-md-6"><strong>PG:</strong> <?php echo htmlspecialchars($r['pg_name']); ?></div>
        <div class="col-md-6"><strong>Address:</strong> <?php echo htmlspecialchars(($r['address'] ?? '') . ', ' . ($r['city'] ?? '')); ?></div>
        <div class="col-md-6"><strong>Join date:</strong> <?php echo htmlspecialchars($r['move_in_date'] ?: '-'); ?></div>
        <div class="col-md-6"><strong>Amount paid:</strong> ₹<?php echo number_format((float)$r['payment_amount'], 2); ?></div>
        <div class="col-md-6"><strong>Payment ref:</strong> <?php echo htmlspecialchars($r['payment_ref'] ?: '-'); ?></div>
        <div class="col-md-6"><strong>Status:</strong> <?php echo htmlspecialchars($r['payment_status'] ?: 'unpaid'); ?></div>
        <div class="col-md-6"><strong>Paid at:</strong> <?php echo htmlspecialchars($r['paid_at'] ?: '-'); ?></div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
