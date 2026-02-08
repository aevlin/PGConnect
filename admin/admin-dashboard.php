<?php
// admin/admin-dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../backend/login.php');
    exit;
}

$adminName = $_SESSION['user_name'] ?? 'Admin';
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/booking_schema.php';

ensure_bookings_schema($pdo);

// Stats
$totalPgs = $pendingPgs = $totalUsers = $totalBookings = 0;
try {
  $totalPgs = (int)$pdo->query("SELECT COUNT(*) FROM pg_listings")->fetchColumn();
  $pendingPgs = (int)$pdo->query("SELECT COUNT(*) FROM pg_listings WHERE status = 'pending'")->fetchColumn();
  $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
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
          <a href="admin-approve.php" class="small text-primary">Open approvals →</a>
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
  </div>
</main>

<?php require_once '../includes/footer.php'; ?>
