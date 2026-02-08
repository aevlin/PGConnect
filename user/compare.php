<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../backend/login.php'); exit; }

$ids = $_SESSION['compare_pgs'] ?? [];
$rows = [];
if (!empty($ids)) {
    $in = implode(',', array_map('intval', $ids));
    $stmt = $pdo->query("SELECT p.id, p.pg_name, p.city, p.monthly_rent, p.capacity, p.sharing_type,
            (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image
        FROM pg_listings p WHERE p.id IN ($in)");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<section class="section-shell">
  <div class="container py-4">
    <h1 class="h5 mb-3">Compare PGs</h1>
    <?php if (empty($rows)): ?>
      <div class="alert alert-info">No PGs selected for comparison.</div>
    <?php else: ?>
      <div class="row g-3">
      <?php foreach ($rows as $row): 
        $img = $row['cover_image'] ?: '';
        $fallback = 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=900';
        if (empty($img)) $img = $fallback;
        else $img = pg_image_url($img, $fallback);
      ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
              <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" style="height:200px;object-fit:cover;" alt="PG photo">
              <div class="card-body">
                <h5><?php echo htmlspecialchars($row['pg_name']); ?></h5>
                <p class="text-muted mb-1"><?php echo htmlspecialchars($row['city']); ?></p>
                <p class="mb-1">₹<?php echo number_format($row['monthly_rent']); ?>/month</p>
                <p class="mb-0"><?php echo (int)$row['capacity']; ?> beds · <?php echo htmlspecialchars(ucfirst($row['sharing_type'])); ?></p>
                <button class="btn btn-outline-danger btn-sm mt-2 compare-btn" data-pg="<?php echo $row['id']; ?>">Remove</button>
              </div>
            </div>
          </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
