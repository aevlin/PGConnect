<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/config.php';
require_once '../backend/favorites_schema.php';
require_once '../backend/reviews_schema.php';

// session started in header if needed
?>

<section class="section-shell">
  <div class="container py-5">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h4 mb-0">PG Listings</h1>
            <small class="text-muted">Find your perfect PG accommodation</small>
        </div>
        <div class="col-auto">
            <a href="search-filters.php" class="btn btn-outline-primary">Filters</a>
        </div>
    </div>

    <div class="row g-4">
        <?php
        $statusWhere = (defined('SHOW_PENDING_LISTINGS') && SHOW_PENDING_LISTINGS) ? "IN ('approved','pending')" : "= 'approved'";
        $stmt = $pdo->query("SELECT p.id, p.pg_name, p.city, p.address, p.monthly_rent, p.capacity, p.sharing_type,
                                    (SELECT image_path FROM pg_images WHERE pg_id = p.id LIMIT 1) AS cover_image
                             FROM pg_listings p
                             WHERE p.status $statusWhere
                             ORDER BY p.created_at DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // favorites for current user (if logged in)
    $favIds = [];
    if (isset($_SESSION['user_id'])) {
        try {
            ensure_favorites_schema($pdo);
            $f = $pdo->prepare('SELECT pg_id FROM favorites WHERE user_id = ?');
            $f->execute([$_SESSION['user_id']]);
            $favIds = $f->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $favIds = [];
        }
    }

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $img = $row['cover_image'] ?: '';
                $fallback = 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?w=900&h=600';
                if (empty($img)) {
                    $img = $fallback;
                } else {
                    $img = pg_image_url($img, $fallback);
                }
        ?>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 shadow-sm border-0 position-relative">
                    <?php $isFav = isset($favIds) ? in_array($row['id'], $favIds) : false; ?>
                    <button class="fav-btn fav-heart-btn <?php echo $isFav ? 'active' : ''; ?>" data-pg="<?php echo $row['id']; ?>" aria-label="Save PG">
                        <i class="fa-solid fa-heart"></i>
                    </button>
                    <img src="<?php echo htmlspecialchars($img); ?>"
                         class="card-img-top" 
                         style="height: 200px; object-fit: cover;"
                         alt="PG photo">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title mb-2">
                            <?php echo htmlspecialchars($row['pg_name']); ?>
                        </h5>
                        <p class="card-text mb-1 text-muted">
                            <?php echo htmlspecialchars($row['city']); ?>
                        </p>
                        <?php $rating = pg_fallback_rating((int)$row['id']); ?>
                        <div class="mb-2"><span class="rating-badge">★ <?php echo htmlspecialchars($rating); ?></span></div>
                        <p class="card-text mb-2">
                            <strong>₹<?php echo number_format($row['monthly_rent'], 0); ?>/month</strong>
                        </p>
                        <p class="card-text mb-3">
                            <?php echo $row['capacity']; ?> beds • 
                            <span class="badge bg-light text-dark border">
                                <?php echo ucfirst($row['sharing_type']); ?>
                            </span>
                        </p>
                        <a href="pg-detail.php?id=<?php echo $row['id']; ?>"
                           class="btn btn-success mt-auto">View Details</a>
                        <button class="btn btn-outline-secondary compare-btn mt-2" data-pg="<?php echo $row['id']; ?>">Compare</button>
                    </div>
                </div>
            </div>
        <?php
            }
        } else {
            echo '<div class="col-12"><div class="alert alert-info">No PGs available right now. Check back soon!</div></div>';
        }
        ?>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
