<?php
// owner/owner-pg-list.php (uses shared header/footer)
require_once '../backend/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
  header('Location: ' . BASE_URL . '/backend/login.php');
  exit;
}

$ownerId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare(
  'SELECT p.id, p.pg_name, p.city, p.address AS location, p.monthly_rent AS rent, p.capacity AS vacancy, p.status,
      (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
   FROM pg_listings p
   WHERE owner_id = :owner_id
   ORDER BY created_at DESC'
);
$stmt->execute([':owner_id' => $ownerId]);
$pgs = $stmt->fetchAll();
require_once '../includes/header.php';
?>

<div class="container py-4">
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success">Operation completed successfully.</div>
  <?php endif; ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h5 mb-1">Your PG listings</h1>
      <p class="small text-muted mb-0">All PGs added from your owner account.</p>
    </div>
    <a href="owner-dashboard.php" class="small text-muted text-decoration-none">← Back to dashboard</a>
  </div>

  <?php if (empty($pgs)): ?>
    <div class="alert alert-info small">
      You have not added any PGs yet. <a href="owner-add-pg.php" class="alert-link">Add your first PG</a>.
    </div>
  <?php else: ?>
    <form id="ownerBulkForm" method="POST" action="owner-bulk-action.php">
    <table class="table table-sm align-middle bg-white shadow-sm">
      <thead class="table-light">
        <tr>
          <th style="width:36px;"><input id="selectAll" type="checkbox"></th>
          <th>ID</th>
          <th>PG name</th>
          <th>City</th>
          <th>Area</th>
          <th>Rent (₹)</th>
          <th>Vacancy</th>
          <th>Status</th>
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
            if (empty($img)) {
                $img = '/uploads/default-pg.jpg';
            } else {
                if (!preg_match('#^(https?://|/)#', $img)) $img = '/' . $img;
            }
            ?>
            <div class="d-flex gap-2 align-items-center">
              <img src="<?php echo htmlspecialchars($img); ?>" style="width:64px;height:48px;object-fit:cover;border-radius:6px;" alt="thumb">
              <span><?php echo htmlspecialchars($pg['pg_name']); ?></span>
            </div>
          </td>
          <td><?php echo htmlspecialchars($pg['city']); ?></td>
          <td><?php echo htmlspecialchars($pg['location']); ?></td>
          <td><?php echo (int)$pg['rent']; ?></td>
          <td><?php echo (int)$pg['vacancy']; ?></td>
          <td>
            <?php if ($pg['status'] === 'approved'): ?>
              <span class="badge bg-success">Approved</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark">Pending</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-2">
              <a href="owner-add-pg.php?id=<?php echo (int)$pg['id']; ?>" class="btn btn-sm btn-gradient">Edit</a>
              <a href="owner-availability.php?pg_id=<?php echo (int)$pg['id']; ?>" class="btn btn-sm btn-outline-secondary">Availability</a>
              <a href="owner-bookings.php?pg_id=<?php echo (int)$pg['id']; ?>" class="btn btn-sm btn-outline-primary">Bookings</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="d-flex gap-2 mb-4">
      <button type="submit" name="action" value="delete" class="btn btn-outline-danger">Delete selected</button>
      <button type="submit" name="action" value="mark_draft" class="btn btn-outline-secondary">Mark Draft</button>
    </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
