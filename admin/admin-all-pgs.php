<?php
// admin/admin-all-pgs.php
session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header('Location: ' . BASE_URL . '/backend/login.php');
  exit;
}
require_once '../includes/header.php';
require_once '../backend/connect.php';

$pgs = [];
$queryError = '';
try {
    $stmt = $pdo->query("SELECT p.id, p.pg_name, p.city, p.address AS location, p.monthly_rent AS rent, p.status, u.name AS owner_name,
           (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
         FROM pg_listings p
         JOIN users u ON p.owner_id = u.id
         ORDER BY p.created_at DESC");
    $pgs = $stmt->fetchAll();
} catch (Throwable $e) {
    // log and don't let the global handler render the friendly page; instead show an inline alert
    $log = __DIR__ . '/../backend/error.log';
    @mkdir(dirname($log), 0755, true);
    @file_put_contents($log, date('Y-m-d H:i:s') . " ADMIN-ALL-PGS ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Database error while fetching listings.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>All PGs – Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">All PG listings</h1>
    <a href="admin-dashboard.php" class="small text-muted text-decoration-none">← Back to dashboard</a>
  </div>

  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <form id="adminBulkForm" method="POST" action="admin-bulk-approve.php">
  <table class="table table-sm align-middle bg-white shadow-sm">
    <thead class="table-light">
      <tr>
        <th style="width:36px;"><input id="adminSelectAll" type="checkbox"></th>
        <th>ID</th>
        <th>PG name</th>
        <th>Owner</th>
        <th>City</th>
        <th>Rent (₹)</th>
        <th>Status</th>
        <th style="width: 160px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pgs as $pg): ?>
      <tr>
        <td><input type="checkbox" name="pg_ids[]" value="<?php echo (int)$pg['id']; ?>"></td>
        <td><?php echo (int)$pg['id']; ?></td>
        <td>
          <?php
          $img = $pg['cover_image'] ?? '';
          $fallback = pg_image_url('uploads/default-pg.jpg');
          if (empty($img)) $img = $fallback;
          else $img = pg_image_url($img, $fallback);
          ?>
          <div class="d-flex gap-2 align-items-center">
            <img src="<?php echo htmlspecialchars($img); ?>" style="width:64px;height:48px;object-fit:cover;border-radius:6px;" alt="thumb">
            <span><?php echo htmlspecialchars($pg['pg_name']); ?></span>
          </div>
        </td>
        <td><?php echo htmlspecialchars($pg['owner_name']); ?></td>
        <td><?php echo htmlspecialchars($pg['city']); ?></td>
        <td><?php echo (int)$pg['rent']; ?></td>
        <td>
          <?php if ($pg['status'] === 'approved'): ?>
            <span class="badge bg-success">Approved</span>
          <?php else: ?>
            <span class="badge bg-warning text-dark">Pending</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-success btn-sm admin-action" data-id="<?php echo (int)$pg['id']; ?>" data-action="approve">Approve</button>
            <button type="button" class="btn btn-outline-danger btn-sm admin-action" data-id="<?php echo (int)$pg['id']; ?>" data-action="reject">Reject</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="d-flex gap-2 mb-4">
    <button type="submit" name="bulk_action" value="approve" class="btn btn-success">Approve selected</button>
    <button type="submit" name="bulk_action" value="reject" class="btn btn-outline-danger">Reject selected</button>
  </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('adminBulkForm');
  const selectAll = document.getElementById('adminSelectAll');

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('input[name="pg_ids[]"]').forEach(cb => cb.checked = this.checked);
    });
  }

  document.querySelectorAll('.admin-action').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const id = this.dataset.id;
      const action = this.dataset.action;
      if (!id || !action) return;

      // optional confirmation for reject
      if (action === 'reject' && !confirm('Are you sure you want to reject this listing?')) return;

      // remove any previous temporary inputs added by JS
      form.querySelectorAll('.temp-input').forEach(el => el.remove());

      // uncheck all checkboxes so only the intended id is submitted
      document.querySelectorAll('input[name="pg_ids[]"]').forEach(cb => cb.checked = false);

      // create hidden input for single id
      const hid = document.createElement('input');
      hid.type = 'hidden';
      hid.name = 'pg_ids[]';
      hid.value = id;
      hid.className = 'temp-input';
      form.appendChild(hid);

      // create hidden input for action
      const actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'bulk_action';
      actionInput.value = action;
      actionInput.className = 'temp-input';
      form.appendChild(actionInput);

      // disable visible submit buttons so they don't interfere with our hidden inputs
      const submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
      submits.forEach(s => s.disabled = true);

      form.submit();
    });
  });
});
</script>

<?php require_once '../includes/footer.php'; ?>
