<?php
require_once '../backend/bootstrap.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/user_schema.php';

ensure_user_profile_schema($pdo);

$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$owners = [];
$queryError = '';
try {
    $sql = "SELECT id, name, email, phone, address, dob, profile_photo, owner_aadhaar, owner_permit, owner_verification_status
            FROM users
            WHERE role = 'owner'";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (LOWER(name) LIKE :q OR LOWER(email) LIKE :q OR LOWER(phone) LIKE :q)";
        $params['q'] = '%' . strtolower($q) . '%';
    }
    if (in_array($statusFilter, ['pending', 'verified', 'rejected'], true)) {
        $sql .= " AND COALESCE(owner_verification_status, 'pending') = :status";
        $params['status'] = $statusFilter;
    }
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Failed to load owners.';
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h5 mb-0">Owner verification</h1>
      <p class="small text-muted mb-0">Review owner documents and approve accounts.</p>
    </div>
    <a href="admin-dashboard.php" class="btn btn-outline-secondary">← Back to dashboard</a>
  </div>

  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>

  <form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label small text-muted mb-1">Search owners</label>
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name, email, or phone">
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted mb-1">Verification</label>
        <select class="form-select" name="status">
          <option value="">All statuses</option>
          <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
          <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-primary w-100">Filter</button>
        <a href="admin-owners.php" class="btn btn-outline-secondary">Clear</a>
      </div>
    </div>
  </form>

  <?php if (empty($owners)): ?>
    <div class="alert alert-info">No owner accounts found.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle bg-white shadow-sm">
        <thead class="table-light">
          <tr>
            <th>Owner</th>
            <th>Contact</th>
            <th>Documents</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($owners as $o): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($o['profile_photo'])): ?>
                    <img src="<?php echo htmlspecialchars(pg_image_url($o['profile_photo'])); ?>" style="width:42px;height:42px;object-fit:cover;border-radius:50%;" alt="photo">
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($o['name']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($o['email']); ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="small"><?php echo htmlspecialchars($o['phone'] ?? ''); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($o['address'] ?? ''); ?></div>
              </td>
              <td>
                <?php if (!empty($o['owner_aadhaar'])): ?>
                  <a href="<?php echo htmlspecialchars(pg_image_url($o['owner_aadhaar'])); ?>" target="_blank">Aadhaar</a>
                <?php else: ?>
                  <span class="text-muted small">No Aadhaar</span>
                <?php endif; ?>
                <br>
                <?php if (!empty($o['owner_permit'])): ?>
                  <a href="<?php echo htmlspecialchars(pg_image_url($o['owner_permit'])); ?>" target="_blank">Permit</a>
                <?php else: ?>
                  <span class="text-muted small">No Permit</span>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?php echo htmlspecialchars($o['owner_verification_status'] ?? 'pending'); ?></span></td>
              <td>
                <div class="d-flex gap-1">
                  <a class="btn btn-sm btn-success" href="admin-owner-action.php?id=<?php echo (int)$o['id']; ?>&action=approve">Approve</a>
                  <a class="btn btn-sm btn-outline-danger" href="admin-owner-action.php?id=<?php echo (int)$o['id']; ?>&action=reject">Reject</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
