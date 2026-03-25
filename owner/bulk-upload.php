<?php
$page_title = 'Bulk Upload PGs';
require_once '../backend/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
  header('Location: ../backend/login.php');
  exit;
}

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
require_once '../includes/header.php';
?>

<section class="section-shell">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <h2 class="h4 mb-3">Bulk upload PG listings (CSV)</h2>
        <p class="text-muted mb-4">Upload a CSV file to add multiple PG listings in one go. Each row becomes a pending listing and will be approved by admin.</p>

        <?php if ($msg): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
          <div class="card-body">
            <form action="../backend/importPgs.php" method="POST" enctype="multipart/form-data">
              <div class="mb-3">
                <label class="form-label">CSV file (UTF-8) *</label>
                <input type="file" name="csvfile" accept=".csv" class="form-control" required>
              </div>
              <div class="mb-3">
                <small class="text-muted">Required columns (header row): <code>pg_code, pg_name, district, state, location_area, city, address, monthly_rent, capacity, available_beds, occupancy_type, occupancy_status, sharing_type, latitude, longitude, amenities</code></small>
              </div>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-gradient">Upload and Import</button>
                <a href="pg-bulk-template.csv" class="btn btn-outline-secondary">Download template</a>
                <a href="owner-dashboard.php" class="btn btn-outline-secondary ms-auto">← Dashboard</a>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body small text-muted">
            <strong>Notes:</strong>
            <ul>
              <li>Maximum rows: 2000. Invalid rows are skipped and reported.</li>
              <li>Valid occupancy types: <code>boys</code>, <code>girls</code>, <code>co-ed</code>. Valid occupancy statuses: <code>available</code>, <code>filling_fast</code>, <code>full</code>.</li>
              <li>Sharing type must be one of: single, double, triple.</li>
              <li>Listings are created with status <code>pending</code> and require admin approval.</li>
            </ul>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
