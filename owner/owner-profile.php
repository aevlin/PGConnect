<?php
require_once '../backend/connect.php';
require_once '../backend/user_schema.php';
require_once '../backend/upload_helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'owner') {
    header('Location: ../backend/login.php'); exit;
}

ensure_user_profile_schema($pdo);
$userId = (int)$_SESSION['user_id'];
$error = $success = '';

// fetch current data
$stmt = $pdo->prepare('SELECT name, email, phone, address, dob, profile_photo, owner_aadhaar, owner_permit, owner_verification_status FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dob = trim($_POST['dob'] ?? '');

    if ($name === '') {
        $error = 'Name is required';
    } else {
        $photoPath = $u['profile_photo'] ?? null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/profiles';
            $uploadError = '';
            $fname = pg_store_upload($_FILES['profile_photo'], $uploadDir, 'owner_' . $userId . '_photo', ['jpg','jpeg','png','webp'], ['image/'], 4 * 1024 * 1024, $uploadError);
            if ($fname !== null) {
                $photoPath = 'uploads/profiles/' . $fname;
            } else {
                $error = $uploadError ?: 'Invalid profile photo upload.';
            }
        }

        $aadhaarPath = $u['owner_aadhaar'] ?? null;
        if ($error === '' && !empty($_FILES['owner_aadhaar']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/docs';
            $uploadError = '';
            $fname = pg_store_upload($_FILES['owner_aadhaar'], $uploadDir, 'owner_' . $userId . '_aadhaar', ['jpg','jpeg','png','pdf'], ['image/','application/pdf'], 6 * 1024 * 1024, $uploadError);
            if ($fname !== null) {
                $aadhaarPath = 'uploads/docs/' . $fname;
            } else {
                $error = $uploadError ?: 'Invalid Aadhaar upload.';
            }
        }

        $permitPath = $u['owner_permit'] ?? null;
        if ($error === '' && !empty($_FILES['owner_permit']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/docs';
            $uploadError = '';
            $fname = pg_store_upload($_FILES['owner_permit'], $uploadDir, 'owner_' . $userId . '_permit', ['jpg','jpeg','png','pdf'], ['image/','application/pdf'], 6 * 1024 * 1024, $uploadError);
            if ($fname !== null) {
                $permitPath = 'uploads/docs/' . $fname;
            } else {
                $error = $uploadError ?: 'Invalid permit upload.';
            }
        }
        if ($error === '') {
            $upd = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ?, dob = ?, profile_photo = ?, owner_aadhaar = ?, owner_permit = ?, owner_verification_status = ? WHERE id = ?');
            $upd->execute([$name, $phone, $address, $dob ?: null, $photoPath, $aadhaarPath, $permitPath, 'pending', $userId]);
            $success = 'Profile updated. Verification pending.';
            $u['name'] = $name; $u['phone'] = $phone; $u['address'] = $address; $u['dob'] = $dob; $u['profile_photo'] = $photoPath; $u['owner_aadhaar'] = $aadhaarPath; $u['owner_permit'] = $permitPath; $u['owner_verification_status'] = 'pending';
            $_SESSION['user_name'] = $name;
        }
    }
}
require_once '../includes/header.php';
?>

<section class="section-shell">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm p-4">
          <h1 class="h5 mb-3">Owner Profile</h1>
          <p class="small text-muted">Verification status: <strong><?php echo htmlspecialchars($u['owner_verification_status'] ?? 'pending'); ?></strong></p>
          <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
          <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
              <label class="form-label small mb-1">Full name</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($u['name'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Email (readonly)</label>
              <input type="email" class="form-control" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Address</label>
              <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($u['address'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Date of birth</label>
              <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($u['dob'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Profile photo</label>
              <input type="file" name="profile_photo" class="form-control" accept="image/*">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Aadhaar (image/PDF)</label>
              <input type="file" name="owner_aadhaar" class="form-control" accept=".pdf,image/*">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Listing Permit (image/PDF)</label>
              <input type="file" name="owner_permit" class="form-control" accept=".pdf,image/*">
            </div>
            <button class="btn btn-primary">Save changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>
