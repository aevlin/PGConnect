<?php
require_once '../backend/connect.php';
require_once '../backend/favorites_schema.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../backend/login.php'); exit;
}

ensure_favorites_schema($pdo);
$userId = (int)$_SESSION['user_id'];
$rows = [];
$queryError = '';
try {
    $stmt = $pdo->prepare("SELECT p.id, p.pg_name, p.city, p.address, p.monthly_rent, p.capacity, p.sharing_type,
                              (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image
                           FROM favorites f
                           JOIN pg_listings p ON p.id = f.pg_id
                           WHERE f.user_id = ?
                           ORDER BY f.created_at DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $queryError = (defined('DEV_MODE') && DEV_MODE) ? $e->getMessage() : 'Failed to load saved PGs.';
}
require_once '../includes/header.php';
?>

<div class="container py-5">
  <h1 class="h4 mb-3">Saved PGs</h1>
  <?php if ($queryError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($queryError); ?></div>
  <?php endif; ?>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">You have no saved PGs yet. Use the search to find and save listings.</div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($rows as $row): 
        $img = $row['cover_image'] ?: '';
        $fallback = pg_fallback_image((int)$row['id']);
        if (empty($img)) $img = $fallback;
        else $img = pg_image_url($img, $fallback);
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm border-0 position-relative">
            <button class="fav-btn fav-heart-btn active" data-pg="<?php echo $row['id']; ?>" aria-label="Unsave PG">
              <i class="fa-solid fa-heart"></i>
            </button>
            <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" style="height:200px;object-fit:cover;" alt="PG photo">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?php echo htmlspecialchars($row['pg_name']); ?></h5>
              <p class="card-text text-muted"><?php echo htmlspecialchars($row['city']); ?></p>
              <p class="card-text"><strong>₹<?php echo number_format($row['monthly_rent']); ?>/month</strong></p>
              <p class="card-text mb-3">
                <?php echo htmlspecialchars($row['capacity']); ?> beds • 
                <span class="badge bg-light text-dark"><?php echo htmlspecialchars(ucfirst($row['sharing_type'])); ?></span>
              </p>
              <a href="pg-detail.php?id=<?php echo $row['id']; ?>" class="btn btn-success mt-auto">View Details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
