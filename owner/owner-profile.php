<?php
require_once '../includes/header.php';
require_once '../backend/connect.php';
require_once '../backend/user_schema.php';
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
            $uploadDir = __DIR__ . '/../uploads/profiles/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $fname = 'owner_' . $userId . '_photo_' . time() . '.' . $ext;
            $target = $uploadDir . $fname;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target)) {
                $photoPath = 'uploads/profiles/' . $fname;
            }
        }

        $aadhaarPath = $u['owner_aadhaar'] ?? null;
        if (!empty($_FILES['owner_aadhaar']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/docs/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['owner_aadhaar']['name'], PATHINFO_EXTENSION));
            $fname = 'owner_' . $userId . '_aadhaar_' . time() . '.' . $ext;
            $target = $uploadDir . $fname;
            if (move_uploaded_file($_FILES['owner_aadhaar']['tmp_name'], $target)) {
                $aadhaarPath = 'uploads/docs/' . $fname;
            }
        }

        $permitPath = $u['owner_permit'] ?? null;
        if (!empty($_FILES['owner_permit']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/docs/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['owner_permit']['name'], PATHINFO_EXTENSION));
            $fname = 'owner_' . $userId . '_permit_' . time() . '.' . $ext;
            $target = $uploadDir . $fname;
            if (move_uploaded_file($_FILES['owner_permit']['tmp_name'], $target)) {
                $permitPath = 'uploads/docs/' . $fname;
            }
        }

        $upd = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ?, dob = ?, profile_photo = ?, owner_aadhaar = ?, owner_permit = ?, owner_verification_status = ? WHERE id = ?');
        $upd->execute([$name, $phone, $address, $dob ?: null, $photoPath, $aadhaarPath, $permitPath, 'pending', $userId]);
        $success = 'Profile updated. Verification pending.';
        $u['name'] = $name; $u['phone'] = $phone; $u['address'] = $address; $u['dob'] = $dob; $u['profile_photo'] = $photoPath; $u['owner_aadhaar'] = $aadhaarPath; $u['owner_permit'] = $permitPath; $u['owner_verification_status'] = 'pending';
        $_SESSION['user_name'] = $name;
    }
}
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
