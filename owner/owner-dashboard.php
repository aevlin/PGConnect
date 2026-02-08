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
            <?php
                $stmt = $pdo->prepare('SELECT p.id, p.pg_name, p.city, p.address, p.monthly_rent, p.capacity, p.sharing_type, p.status,
                                                     (SELECT image_path FROM pg_images WHERE pg_id = p.id ORDER BY id LIMIT 1) AS cover_image
                                                 FROM pg_listings p
                                                 WHERE owner_id = :oid
                                                 ORDER BY created_at DESC');
            $stmt->execute([':oid' => $ownerId]);
            $myPgs = $stmt->fetchAll();
            ?>

            <div class="row g-3 mb-4">
            <?php if (empty($myPgs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">You have not added any PGs yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($myPgs as $pg): ?>
                <div class="col-md-6 col-lg-4">
                    <article class="pg-card h-100 p-0">
                        <?php
                        $img = $pg['cover_image'] ?? '';
                        $fallback = pg_image_url('uploads/default-pg.jpg');
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
