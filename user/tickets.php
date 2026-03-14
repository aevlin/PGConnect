<?php
require_once '../backend/auth.php';
require_role('user');
require_once '../backend/connect.php';
require_once '../backend/feature_schema.php';
require_once '../backend/notify.php';
ensure_feature_schema($pdo);
require_once '../includes/header.php';

$userId = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $category = trim($_POST['category'] ?? 'general');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($bookingId > 0 && $title !== '') {
        $b = $pdo->prepare("SELECT b.id, b.pg_id, p.owner_id FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE b.id = ? AND b.user_id = ? AND b.status = 'paid' LIMIT 1");
        $b->execute([$bookingId, $userId]);
        $row = $b->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $ins = $pdo->prepare('INSERT INTO service_tickets (booking_id, pg_id, user_id, owner_id, category, title, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$bookingId, $row['pg_id'], $userId, $row['owner_id'], $category, $title, $description, 'open']);
            notify_user($pdo, (int)$row['owner_id'], 'owner', 'New service ticket', $title, '/PGConnect/owner/tickets.php');
            $msg = 'Ticket created.';
        }
    }
}

$bookings = $pdo->prepare("SELECT b.id, p.pg_name FROM bookings b JOIN pg_listings p ON p.id=b.pg_id WHERE b.user_id = ? AND b.status='paid' ORDER BY b.created_at DESC");
$bookings->execute([$userId]);
$bookingRows = $bookings->fetchAll(PDO::FETCH_ASSOC);

$tickets = $pdo->prepare("SELECT t.*, p.pg_name FROM service_tickets t JOIN pg_listings p ON p.id=t.pg_id WHERE t.user_id = ? ORDER BY t.created_at DESC");
$tickets->execute([$userId]);
$rows = $tickets->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container py-4">
  <h1 class="h5 mb-3">Service Tickets</h1>
  <?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h6">Raise Issue</h2>
      <form method="POST" class="row g-2">
        <div class="col-md-3">
          <label class="form-label">Booking</label>
          <select name="booking_id" class="form-select" required>
            <option value="">Select booking</option>
            <?php foreach ($bookingRows as $b): ?>
              <option value="<?php echo (int)$b['id']; ?>"><?php echo htmlspecialchars($b['pg_name'] . ' (#' . $b['id'] . ')'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Category</label>
          <select name="category" class="form-select">
            <option value="water">Water</option>
            <option value="wifi">Wi-Fi</option>
            <option value="security">Security</option>
            <option value="electricity">Electricity</option>
            <option value="general">General</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control">
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Submit ticket</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h2 class="h6">My Tickets</h2>
      <?php if (empty($rows)): ?>
        <p class="small text-muted mb-0">No tickets yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead><tr><th>ID</th><th>PG</th><th>Category</th><th>Title</th><th>Status</th><th>Owner note</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['pg_name']); ?></td>
                <td><?php echo htmlspecialchars($r['category']); ?></td>
                <td><?php echo htmlspecialchars($r['title']); ?></td>
                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($r['status']); ?></span></td>
                <td><?php echo htmlspecialchars($r['owner_note'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
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

