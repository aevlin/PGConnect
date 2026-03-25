<?php
require_once '../backend/auth.php';
require_role('user');
require_once '../backend/connect.php';
require_once '../backend/feature_schema.php';
ensure_feature_schema($pdo);

$userId = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $city = trim($_POST['city'] ?? '');
        $min = ($_POST['min_rent'] ?? '') !== '' ? (int)$_POST['min_rent'] : null;
        $max = ($_POST['max_rent'] ?? '') !== '' ? (int)$_POST['max_rent'] : null;
        $sharing = trim($_POST['sharing'] ?? '');
        $ins = $pdo->prepare('INSERT INTO saved_searches (user_id, city, min_rent, max_rent, sharing, is_active, last_match_count) VALUES (?, ?, ?, ?, ?, 1, 0)');
        $ins->execute([$userId, $city, $min, $max, $sharing]);
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0);
        $u = $pdo->prepare('UPDATE saved_searches SET is_active = ? WHERE id = ? AND user_id = ?');
        $u->execute([$active, $id, $userId]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $d = $pdo->prepare('DELETE FROM saved_searches WHERE id = ? AND user_id = ?');
        $d->execute([$id, $userId]);
    }
    header('Location: saved-searches.php');
    exit;
}

$rows = $pdo->prepare('SELECT * FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC');
$rows->execute([$userId]);
$items = $rows->fetchAll(PDO::FETCH_ASSOC);
require_once '../includes/header.php';
?>
<div class="container py-4">
  <h1 class="h5 mb-3">Saved Search Alerts</h1>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="POST" class="row g-2">
        <input type="hidden" name="action" value="create">
        <div class="col-md-3"><input type="text" name="city" class="form-control" placeholder="City"></div>
        <div class="col-md-2"><input type="number" name="min_rent" class="form-control" placeholder="Min rent"></div>
        <div class="col-md-2"><input type="number" name="max_rent" class="form-control" placeholder="Max rent"></div>
        <div class="col-md-2">
          <select name="sharing" class="form-select">
            <option value="">Any sharing</option>
            <option value="single">Single</option>
            <option value="double">Double</option>
            <option value="triple">Triple</option>
          </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Save alert</button></div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?php if (empty($items)): ?>
        <p class="small text-muted mb-0">No saved searches yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>City</th><th>Rent</th><th>Sharing</th><th>Active</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td><?php echo htmlspecialchars($it['city'] ?: 'Any'); ?></td>
                  <td><?php echo htmlspecialchars(($it['min_rent'] ?: '0') . ' - ' . ($it['max_rent'] ?: 'Any')); ?></td>
                  <td><?php echo htmlspecialchars($it['sharing'] ?: 'Any'); ?></td>
                  <td><?php echo (int)$it['is_active'] ? 'Yes' : 'No'; ?></td>
                  <td class="text-end">
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                      <input type="hidden" name="is_active" value="<?php echo (int)$it['is_active'] ? 0 : 1; ?>">
                      <button class="btn btn-sm btn-outline-secondary"><?php echo (int)$it['is_active'] ? 'Pause' : 'Activate'; ?></button>
                    </form>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                      <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
