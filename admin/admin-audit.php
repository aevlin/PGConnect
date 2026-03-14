<?php
require_once '../backend/auth.php';
require_once '../backend/connect.php';
require_once '../backend/system_schema.php';
require_role('admin');
ensure_system_schema($pdo);
require_once '../includes/header.php';

$rows = [];
try {
    $stmt = $pdo->query('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 200');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">Audit Trail</h1>
    <a href="admin-dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
  </div>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">No audit entries yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm bg-white shadow-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>Time</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Target</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              <td><?php echo htmlspecialchars(($r['actor_role'] ?: 'system') . ' #' . (string)($r['actor_id'] ?? '-')); ?></td>
              <td><?php echo htmlspecialchars($r['action']); ?></td>
              <td><?php echo htmlspecialchars((string)($r['target_type'] ?? '-') . ' #' . (string)($r['target_id'] ?? '-')); ?></td>
              <td class="small text-muted"><?php echo htmlspecialchars($r['details'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>

