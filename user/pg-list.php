<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/config.php';

$statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
$sql = "SELECT p.id, p.pg_name, p.city, p.monthly_rent, p.sharing_type,
        (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image
        FROM pg_listings p
        WHERE p.status $statusWhere";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="section-shell">
  <div class="container py-4">
    <?php if (!isset($_SESSION['user_id'])): ?>
      <div class="alert alert-warning mb-4">
        Please <a href="<?php echo BASE_URL; ?>/backend/login.php">login</a> or
        <a href="<?php echo BASE_URL; ?>/backend/signup.php">sign up</a> to save PGs, compare, or make booking requests.
      </div>
    <?php endif; ?>
    <h1 class="h4 mb-4">Available PGs</h1>

    <div class="row g-3">
      <?php foreach ($rows as $row): 
        $fallback = pg_fallback_image((int)$row['id']);
        $img = $row['cover_image'] ?: '';
        if (empty($img)) {
          $img = $fallback;
        } else {
          $img = pg_image_url($img, $fallback);
        }
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm position-relative">
            <?php if (isset($_SESSION['user_id'])): ?>
              <button class="fav-btn fav-heart-btn" data-pg="<?php echo $row['id']; ?>" aria-label="Save PG">
                <i class="fa-solid fa-heart"></i>
              </button>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars($img); ?>"
                 class="card-img-top"
                 alt="Hostel room">
            <div class="card-body">
              <h5 class="card-title mb-1"><?php echo htmlspecialchars($row['pg_name']); ?></h5>
              <p class="card-text mb-1 text-muted"><?php echo htmlspecialchars($row['city']); ?></p>
              <div class="mb-1"><span class="rating-badge">★ <?php echo htmlspecialchars(pg_fallback_rating((int)$row['id'])); ?></span></div>
              <p class="card-text mb-1">₹<?php echo (int)$row['monthly_rent']; ?> / month · <?php echo htmlspecialchars(ucfirst($row['sharing_type'])); ?> sharing</p>
              <button class="btn btn-outline-secondary btn-sm compare-btn mt-2" data-pg="<?php echo $row['id']; ?>">Compare</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
