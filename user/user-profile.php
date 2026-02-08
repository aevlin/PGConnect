<?php
// user/user-profile.php
session_start();

// simple auth guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header('Location: ../backend/login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';

ensure_bookings_schema($pdo);

$userId = (int)$_SESSION['user_id'];
$savedCount = $requestCount = $confirmedCount = 0;
 $recentBookings = [];
 $pendingApprovals = [];
try {
  $savedCount = (int)$pdo->query("SELECT COUNT(*) FROM favorites WHERE user_id = {$userId}")->fetchColumn();
} catch (Throwable $e) { $savedCount = 0; }
try {
  $requestCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = {$userId} AND status IN ('requested','owner_approved')")->fetchColumn();
  $confirmedCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = {$userId} AND status = 'user_confirmed'")->fetchColumn();
  $stmt = $pdo->prepare("SELECT b.*, p.pg_name, p.city FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5");
  $stmt->execute([$userId]);
  $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $pendingApprovals = array_filter($recentBookings, function($b){ return ($b['status'] ?? '') === 'owner_approved'; });
} catch (Throwable $e) { $requestCount = 0; $confirmedCount = 0; }
?>

<section class="section-shell">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h1 class="h4 mb-1">Welcome back, <?php echo htmlspecialchars($userName); ?></h1>
        <p class="text-muted mb-0">Track your favorites and booking approvals.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="saved-pgs.php" class="btn btn-outline-primary btn-sm">Saved PGs</a>
        <a href="booking-request.php" class="btn btn-primary btn-sm">Booking Requests</a>
      </div>
    </div>

    <?php if (!empty($pendingApprovals)): ?>
      <div class="alert alert-success">
        <strong>Owner approved your request.</strong> Please confirm to finalize your booking.
        <a href="booking-request.php" class="ms-2">Review now →</a>
      </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="pg-card p-3">
          <div class="small text-muted mb-1">Saved PGs</div>
          <div class="h3 mb-0"><?php echo (int)$savedCount; ?></div>
          <div class="small text-muted">PGs saved to your shortlist</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="pg-card p-3">
          <div class="small text-muted mb-1">Requests sent</div>
          <div class="h3 mb-0"><?php echo (int)$requestCount; ?></div>
          <div class="small text-muted">Waiting for owner approval</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="pg-card p-3">
          <div class="small text-muted mb-1">Confirmed bookings</div>
          <div class="h3 mb-0"><?php echo (int)$confirmedCount; ?></div>
          <div class="small text-muted">Bookings you confirmed</div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="pg-card p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Recent booking requests</h2>
            <a href="booking-request.php" class="small text-primary">Manage →</a>
          </div>
          <?php if (empty($recentBookings)): ?>
            <p class="text-muted small mb-0">No booking requests yet.</p>
          <?php else: ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($recentBookings as $b): ?>
                <li class="d-flex justify-content-between align-items-center border-bottom py-2">
                  <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($b['pg_name']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($b['city']); ?></div>
                  </div>
                  <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['status']); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="pg-card p-3 h-100">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Your saved PGs</h2>
            <a href="saved-pgs.php" class="small text-primary">View all →</a>
          </div>
          <p class="text-muted small mb-0">Save PGs while browsing to compare and decide later.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
