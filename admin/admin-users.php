<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../backend/login.php'); exit;
}
require_once '../backend/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = trim((string)($_POST['action'] ?? ''));
    if ($id > 0 && in_array($action, ['block', 'activate'], true)) {
        $status = $action === 'block' ? 'blocked' : 'active';
        try {
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ? AND role <> ?');
            $stmt->execute([$status, $id, 'admin']);
        } catch (Throwable $e) {
        }
    }
    header('Location: admin-users.php');
    exit;
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
} catch (Throwable $e) {
}

$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$rows = [];
try {
    $sql = "SELECT id, name, email, role, COALESCE(status, 'active') AS status, created_at
            FROM users
            WHERE 1=1";
    $params = [];
    if ($q !== '') {
        $sql .= " AND (LOWER(name) LIKE :q OR LOWER(email) LIKE :q)";
        $params['q'] = '%' . strtolower($q) . '%';
    }
    if (in_array($roleFilter, ['user', 'owner', 'admin'], true)) {
        $sql .= " AND role = :role";
        $params['role'] = $roleFilter;
    }
    if (in_array($statusFilter, ['active', 'blocked'], true)) {
        $sql .= " AND COALESCE(status, 'active') = :status";
        $params['status'] = $statusFilter;
    }
    $sql .= " ORDER BY created_at DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

require_once '../includes/header.php';
?>

<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">Manage Users</h1>
      <div class="small text-muted">View registered users and block suspicious accounts.</div>
    </div>
  </div>

  <form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label small text-muted mb-1">Search</label>
        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Name or email">
      </div>
      <div class="col-md-3">
        <label class="form-label small text-muted mb-1">Role</label>
        <select class="form-select" name="role">
          <option value="">All roles</option>
          <?php foreach (['user' => 'User', 'owner' => 'Owner', 'admin' => 'Admin'] as $value => $label): ?>
            <option value="<?php echo $value; ?>" <?php echo $roleFilter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small text-muted mb-1">Status</label>
        <select class="form-select" name="status">
          <option value="">Any</option>
          <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100">Filter</button>
        <a href="admin-users.php" class="btn btn-outline-secondary">Clear</a>
      </div>
    </div>
  </form>

  <?php if (empty($rows)): ?>
    <div class="alert alert-info">No users found.</div>
  <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Joined</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['role'] ?? 'user'); ?></span></td>
                <td>
                  <span class="badge <?php echo ($row['status'] ?? 'active') === 'blocked' ? 'bg-danger' : 'bg-success'; ?>">
                    <?php echo htmlspecialchars($row['status'] ?? 'active'); ?>
                  </span>
                </td>
                <td class="small text-muted"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                <td class="text-end">
                  <?php if (($row['role'] ?? '') !== 'admin'): ?>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="action" value="<?php echo ($row['status'] ?? 'active') === 'blocked' ? 'activate' : 'block'; ?>">
                      <button class="btn btn-sm <?php echo ($row['status'] ?? 'active') === 'blocked' ? 'btn-outline-success' : 'btn-outline-danger'; ?>">
                        <?php echo ($row['status'] ?? 'active') === 'blocked' ? 'Activate' : 'Block'; ?>
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="small text-muted">Protected</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
