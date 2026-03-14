<?php
// admin/admin-dashboard.php
require_once '../backend/auth.php';
require_role('admin');
$adminName = $_SESSION['user_name'] ?? 'Admin';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';
require_once '../backend/system_schema.php';
require_once '../includes/header.php';

ensure_bookings_schema($pdo);
ensure_system_schema($pdo);

// Stats
$totalPgs = $pendingPgs = $totalUsers = $totalBookings = 0;
$bookingStatusStats = [];
$topCities = [];
try {
  $totalPgs = (int)$pdo->query("SELECT COUNT(*) FROM pg_listings")->fetchColumn();
  $pendingPgs = (int)$pdo->query("SELECT COUNT(*) FROM pg_listings WHERE status = 'pending'")->fetchColumn();
  $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
  $bookingStatusStats = $pdo->query("SELECT status, COUNT(*) cnt FROM bookings GROUP BY status ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
  $topCities = $pdo->query("SELECT city, COUNT(*) cnt FROM pg_listings GROUP BY city ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // ignore for now; show zeros
}
?>

<main class="container pb-5">
  <div class="row g-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-end flex-wrap gap-2">
      <div>
        <h1 class="h4 mb-1">Admin control center</h1>
        <p class="text-muted small mb-0">Approve PGs, manage users, and keep PGConnect safe.</p>
      </div>
      <a href="admin-all-pgs.php" class="btn btn-dark btn-pill px-4 py-2">Review PG listings</a>
    </div>
    <div class="col-12 d-flex gap-2 flex-wrap">
      <a href="export.php?type=bookings" class="btn btn-outline-primary btn-sm">Export Bookings CSV</a>
      <a href="export.php?type=owners" class="btn btn-outline-primary btn-sm">Export Owners CSV</a>
      <a href="export.php?type=revenue" class="btn btn-outline-primary btn-sm">Export Revenue CSV</a>
    </div>
  </div>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card-soft p-3">
        <div class="small text-muted mb-1">Total PGs</div>
        <div class="stat-number"><?php echo (int)$totalPgs; ?></div>
        <div class="small text-muted">All PG listings in system</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card-soft p-3">
        <div class="small text-muted mb-1">Pending approvals</div>
        <div class="stat-number text-warning"><?php echo (int)$pendingPgs; ?></div>
        <div class="small text-muted">PGs waiting for review</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card-soft p-3">
        <div class="small text-muted mb-1">Registered users</div>
        <div class="stat-number"><?php echo (int)$totalUsers; ?></div>
        <div class="small text-muted">Users + owners + admins</div>
      </div>
    </div>
  </div>

  <!-- Management panels -->
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Pending PG approvals</h2>
          <a href="admin-all-pgs.php" class="small text-primary">Open approvals →</a>
        </div>
        <p class="text-muted small mb-0">New PGs added by owners will appear here for you to approve or reject before users can see them.</p>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">User & owner accounts</h2>
          <a href="admin-users.php" class="small text-primary">Manage users →</a>
        </div>
        <p class="text-muted small mb-0">View all registered users, block suspicious accounts, and reset roles if needed.</p>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Booking activity</h2>
          <a href="admin-bookings.php" class="small text-primary">View bookings →</a>
        </div>
        <p class="text-muted small mb-0">Total booking requests: <strong><?php echo (int)$totalBookings; ?></strong></p>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Owner verification</h2>
          <a href="admin-owners.php" class="small text-primary">Review owners →</a>
        </div>
        <p class="text-muted small mb-0">Review owner documents and verify accounts.</p>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Audit trail</h2>
          <a href="admin-audit.php" class="small text-primary">Open logs →</a>
        </div>
        <p class="text-muted small mb-0">Track who approved, paid, booked, or changed records.</p>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <h2 class="h6 mb-2">Booking status analytics</h2>
        <?php if (empty($bookingStatusStats)): ?>
          <p class="small text-muted mb-0">No booking data yet.</p>
        <?php else: ?>
          <?php foreach ($bookingStatusStats as $s): ?>
            <div class="d-flex justify-content-between small border-bottom py-1">
              <span><?php echo htmlspecialchars($s['status']); ?></span>
              <strong><?php echo (int)$s['cnt']; ?></strong>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-soft p-3">
        <h2 class="h6 mb-2">Top listing cities</h2>
        <?php if (empty($topCities)): ?>
          <p class="small text-muted mb-0">No city data yet.</p>
        <?php else: ?>
          <?php foreach ($topCities as $c): ?>
            <div class="d-flex justify-content-between small border-bottom py-1">
              <span><?php echo htmlspecialchars($c['city'] ?: 'Unknown'); ?></span>
              <strong><?php echo (int)$c['cnt']; ?></strong>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php require_once '../includes/footer.php'; ?>
