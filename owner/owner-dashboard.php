<?php
$page_title = 'Owner Dashboard';
require_once '../backend/connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

$ownerId = (int)($_SESSION['user_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
require_once '../includes/header.php';
require_once '../backend/user_schema.php';
ensure_user_profile_schema($pdo);
// owner verification status
$vstmt = $pdo->prepare('SELECT owner_verification_status FROM users WHERE id = ?');
$vstmt->execute([$ownerId]);
$verificationStatus = $vstmt->fetchColumn() ?: 'pending';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Owner Dashboard</h1>
            <p>Welcome <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>!</p>
            <?php if ($verificationStatus !== 'verified'): ?>
              <div class="alert alert-warning">Your owner verification is <strong><?php echo htmlspecialchars($verificationStatus); ?></strong>. Upload documents in <a href="owner-profile.php">My Profile</a>.</div>
            <?php endif; ?>

            <!-- Owner PGs -->
            <form method="GET" class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <input type="text" name="q" class="form-control" style="max-width:420px;" placeholder="Search your PGs by name, code, city, address" value="<?php echo htmlspecialchars($q); ?>">
                    <button class="btn btn-primary">Search</button>
                    <?php if ($q !== ''): ?>
                      <a href="owner-dashboard.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php
            $sql = 'SELECT p.id, p.pg_name, p.pg_code, p.city, p.address, p.monthly_rent, p.capacity, p.sharing_type, p.status,
                           (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
                    FROM pg_listings p
                    WHERE owner_id = :oid';
            $params = [':oid' => $ownerId];
            if ($q !== '') {
                $sql .= ' AND (LOWER(p.pg_name) LIKE :q OR LOWER(p.pg_code) LIKE :q OR LOWER(p.city) LIKE :q OR LOWER(p.address) LIKE :q)';
                $params[':q'] = '%' . strtolower($q) . '%';
            }
            $sql .= ' ORDER BY created_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $myPgs = $stmt->fetchAll();
            ?>

            <div class="row g-3 mb-4">
            <?php if (empty($myPgs)): ?>
                <div class="col-12">
                    <div class="alert alert-info"><?php echo $q !== '' ? 'No PG matched your search.' : 'You have not added any PGs yet.'; ?></div>
                </div>
            <?php else: ?>
                <?php foreach ($myPgs as $pg): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="pg-card h-100 p-0">
                        <?php
                        $img = $pg['cover_image'] ?? '';
                        $fallback = pg_fallback_image((int)$pg['id']);
                        if (empty($img)) {
                            $img = $fallback;
                        } else {
                            $img = pg_image_url($img, $fallback);
                        }
                        ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-100" style="height:180px;object-fit:cover;" alt="PG">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span><?php echo htmlspecialchars($pg['pg_name']); ?></span>
                                <span class="text-warning fw-semibold">₹<?php echo number_format($pg['monthly_rent']); ?>/mo</span>
                            </div>
                            <p class="small text-muted mb-2"><?php echo htmlspecialchars($pg['address']); ?></p>
                            <div class="d-flex gap-2">
                                <span class="tag"><?php echo htmlspecialchars(ucfirst($pg['sharing_type'])); ?></span>
                                <span class="tag"><?php echo (int)$pg['capacity']; ?> beds</span>
                            </div>
                            <div class="mt-3 d-flex gap-2">
                                <a href="owner-pg-list.php" class="btn btn-sm btn-outline-primary">Manage</a>
                                <a href="owner-add-pg.php?id=<?php echo (int)$pg['id']; ?>" class="btn btn-sm btn-gradient">Edit</a>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <?php
                // count pending booking requests for this owner's PGs
                try {
                    $bc = $pdo->prepare('SELECT COUNT(b.id) FROM bookings b JOIN pg_listings p ON p.id = b.pg_id WHERE p.owner_id = ? AND b.status = ?');
                    $bc->execute([$ownerId, 'requested']);
                    $bookingCount = (int)$bc->fetchColumn();
                } catch (Throwable $e) {
                    $bookingCount = 0;
                }
            ?>
            <div class="mt-3">
                <a href="owner-add-pg.php" class="btn btn-success btn-lg">+ Add New PG</a>
                <a href="owner-bookings.php" class="btn btn-outline-primary btn-lg ms-2">View Bookings <?php if ($bookingCount) echo '<span class="badge bg-danger ms-2">' . $bookingCount . '</span>'; ?></a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
