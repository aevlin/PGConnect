<?php
require_once __DIR__ . '/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    @session_set_cookie_params(0, '/');
    session_start();
}

require_once 'connect.php';
require_once 'user_schema.php';
require_once 'upload_helpers.php';
ensure_user_profile_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/backend/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['user_role'] ?? 'user');
if ($role === 'admin') {
    header('Location: ' . BASE_URL . '/admin/admin-dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: ' . BASE_URL . '/logout.php');
    exit;
}

if (
    (int)($user['onboarding_completed'] ?? 0) === 1 ||
    (
        $role === 'owner' &&
        strtolower((string)($user['owner_verification_status'] ?? '')) === 'approved'
    )
) {
    if ($role === 'owner') {
        header('Location: ' . BASE_URL . '/owner/owner-dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/user/user-profile.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $profilePhoto = $user['profile_photo'] ?? '';
    $prefCity = trim($_POST['pref_city'] ?? '');
    $prefBudget = trim($_POST['pref_budget'] ?? '');
    $moveTimeline = trim($_POST['move_timeline'] ?? '');
    $ownerCity = trim($_POST['owner_city'] ?? '');
    $ownerListings = trim($_POST['owner_listings'] ?? '');
    $ownerDocsReady = trim($_POST['owner_docs_ready'] ?? '');
    $ownerAadhaar = $user['owner_aadhaar'] ?? '';
    $ownerPermit = $user['owner_permit'] ?? '';
    $ownerVerificationStatus = $user['owner_verification_status'] ?? 'pending';

    try {
        if (!empty($_FILES['profile_photo']['name'] ?? '')) {
            $profilePhoto = pg_store_upload($_FILES['profile_photo'], dirname(__DIR__) . '/uploads/profiles', 'prof_', ['jpg', 'jpeg', 'png', 'webp', 'gif'], 5 * 1024 * 1024);
        }
        if ($role === 'owner') {
            if (!empty($_FILES['owner_aadhaar']['name'] ?? '')) {
                $ownerAadhaar = pg_store_upload($_FILES['owner_aadhaar'], dirname(__DIR__) . '/uploads/docs', 'aadhaar_', ['jpg', 'jpeg', 'png', 'pdf'], 8 * 1024 * 1024);
            }
            if (!empty($_FILES['owner_permit']['name'] ?? '')) {
                $ownerPermit = pg_store_upload($_FILES['owner_permit'], dirname(__DIR__) . '/uploads/docs', 'permit_', ['jpg', 'jpeg', 'png', 'pdf'], 8 * 1024 * 1024);
            }
            if ($ownerAadhaar !== '' || $ownerPermit !== '') {
                $ownerVerificationStatus = 'pending';
            }
        }

        $update = $pdo->prepare('UPDATE users
            SET phone = ?, address = ?, dob = ?, profile_photo = ?,
                pref_city = ?, pref_budget = ?, move_timeline = ?,
                owner_city = ?, owner_listings = ?, owner_docs_ready = ?,
                owner_aadhaar = ?, owner_permit = ?, owner_verification_status = ?,
                onboarding_completed = 1
            WHERE id = ?');
        $update->execute([
            $phone, $address, $dob !== '' ? $dob : null, $profilePhoto,
            $prefCity !== '' ? $prefCity : null,
            $prefBudget !== '' ? $prefBudget : null,
            $moveTimeline !== '' ? $moveTimeline : null,
            $ownerCity !== '' ? $ownerCity : null,
            $ownerListings !== '' ? $ownerListings : null,
            $ownerDocsReady !== '' ? $ownerDocsReady : null,
            $ownerAadhaar !== '' ? $ownerAadhaar : null,
            $ownerPermit !== '' ? $ownerPermit : null,
            $ownerVerificationStatus,
            $userId
        ]);

        if ($role === 'owner') {
            header('Location: ' . BASE_URL . '/owner/owner-dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/user/user-profile.php');
        }
        exit;
    } catch (Throwable $e) {
        $error = 'Could not save onboarding details. Please try again.';
    }
}

require_once '../includes/header.php';
?>
<section class="section-shell">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="card border-0 shadow-lg rounded-4">
          <div class="card-body p-5">
            <div class="text-center mb-4">
              <div class="eyebrow"><span></span> One-time setup</div>
              <h2 class="hero-title mt-2 mb-1" style="font-size:1.4rem;">Complete your <?php echo $role === 'owner' ? 'owner' : 'tenant'; ?> profile</h2>
              <p class="small text-muted mb-0">We’ll ask a few questions and verification details before you continue.</p>
            </div>

            <?php if ($error !== ''): ?>
              <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small mb-1">Phone number</label>
                  <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ($user['phone'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Date of birth</label>
                  <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($_POST['dob'] ?? ($user['dob'] ?? '')); ?>">
                </div>
                <div class="col-12">
                  <label class="form-label small mb-1">Address</label>
                  <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($_POST['address'] ?? ($user['address'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Profile picture</label>
                  <input type="file" name="profile_photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
                </div>

                <?php if ($role === 'owner'): ?>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Which city is your PG in?</label>
                    <input type="text" name="owner_city" class="form-control" value="<?php echo htmlspecialchars($_POST['owner_city'] ?? ($user['owner_city'] ?? '')); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">How many listings will you add?</label>
                    <select name="owner_listings" class="form-select" required>
                      <option value="">Choose one</option>
                      <?php foreach (['1' => '1 listing', '2_5' => '2 to 5 listings', '6_20' => '6 to 20 listings', '20_plus' => 'More than 20 listings'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($_POST['owner_listings'] ?? ($user['owner_listings'] ?? '')) === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Are your documents ready?</label>
                    <select name="owner_docs_ready" class="form-select" required>
                      <option value="">Choose one</option>
                      <option value="yes" <?php echo (($_POST['owner_docs_ready'] ?? ($user['owner_docs_ready'] ?? '')) === 'yes') ? 'selected' : ''; ?>>Yes, ready now</option>
                      <option value="almost" <?php echo (($_POST['owner_docs_ready'] ?? ($user['owner_docs_ready'] ?? '')) === 'almost') ? 'selected' : ''; ?>>Almost ready</option>
                      <option value="no" <?php echo (($_POST['owner_docs_ready'] ?? ($user['owner_docs_ready'] ?? '')) === 'no') ? 'selected' : ''; ?>>Not yet</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Aadhaar</label>
                    <input type="file" name="owner_aadhaar" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Listing permit</label>
                    <input type="file" name="owner_permit" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                  </div>
                <?php else: ?>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Preferred city</label>
                    <input type="text" name="pref_city" class="form-control" value="<?php echo htmlspecialchars($_POST['pref_city'] ?? ($user['pref_city'] ?? '')); ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Budget range</label>
                    <select name="pref_budget" class="form-select" required>
                      <option value="">Choose budget</option>
                      <?php foreach (['below_5000' => 'Below ₹5,000', '5000_10000' => '₹5,000 - ₹10,000', '10000_15000' => '₹10,000 - ₹15,000', '15000_plus' => '₹15,000+'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($_POST['pref_budget'] ?? ($user['pref_budget'] ?? '')) === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small mb-1">Move-in timeline</label>
                    <select name="move_timeline" class="form-select" required>
                      <option value="">Choose timeline</option>
                      <?php foreach (['immediately' => 'Immediately', 'this_week' => 'Within a week', 'this_month' => 'Within this month', 'just_browsing' => 'Just browsing'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($_POST['move_timeline'] ?? ($user['move_timeline'] ?? '')) === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>
              </div>

              <button type="submit" class="btn btn-gradient w-100 py-2 mt-4">Save and continue</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require_once '../includes/footer.php'; ?>
