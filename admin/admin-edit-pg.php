<?php
session_start();
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header('Location: ' . BASE_URL . '/backend/login.php');
  exit;
}

require_once '../backend/connect.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header('Location: admin-all-pgs.php'); exit; }

// fetch pg
$stmt = $pdo->prepare('SELECT * FROM pg_listings WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$pg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pg) { header('Location: admin-all-pgs.php'); exit; }

// images
$imgStmt = $pdo->prepare('SELECT id, image_path FROM pg_images WHERE pg_id = ? ORDER BY id');
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // delete images
  if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
    foreach ($_POST['delete_images'] as $delId) {
      $delId = (int)$delId;
      $s = $pdo->prepare('SELECT image_path FROM pg_images WHERE id = ? AND pg_id = ? LIMIT 1');
      $s->execute([$delId, $id]);
      $r = $s->fetch(PDO::FETCH_ASSOC);
      if ($r) {
        $path = __DIR__ . '/../' . ltrim($r['image_path'], '/');
        @unlink($path);
        $d = $pdo->prepare('DELETE FROM pg_images WHERE id = ? AND pg_id = ?');
        $d->execute([$delId, $id]);
      }
    }
  }

  // upload new images
  if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $allowed = ['jpg','jpeg','png','gif'];
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
      $name = $_FILES['images']['name'][$i];
      $tmp = $_FILES['images']['tmp_name'][$i];
      $err = $_FILES['images']['error'][$i];
      if ($err !== UPLOAD_ERR_OK) continue;
      $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed, true)) continue;
      $filename = sprintf('pg_%s_%s.%s', $id, uniqid(), $ext);
      $target = $uploadDir . $filename;
      if (move_uploaded_file($tmp, $target)) {
        $webPath = 'uploads/' . $filename;
        $ins = $pdo->prepare('INSERT INTO pg_images (pg_id, image_path) VALUES (?, ?)');
        $ins->execute([$id, $webPath]);
      }
    }
  }

  $success = 'Images updated.';
  // reload images
  $imgStmt->execute([$id]);
  $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../includes/header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">Edit PG Images</h1>
    <a href="admin-all-pgs.php" class="btn btn-outline-secondary">← Back</a>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card border-0 shadow-sm p-3 mb-3">
    <h2 class="h6"><?php echo htmlspecialchars($pg['pg_name']); ?></h2>
    <p class="small text-muted"><?php echo htmlspecialchars($pg['address']); ?>, <?php echo htmlspecialchars($pg['city']); ?></p>
  </div>

  <form method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm p-3">
    <div class="mb-3">
      <label class="form-label">Upload new images</label>
      <input type="file" name="images[]" class="form-control" multiple accept="image/*">
    </div>
    <?php if (!empty($images)): ?>
      <div class="mb-3">
        <label class="form-label">Existing images (select to delete)</label>
        <div class="row g-2">
          <?php foreach ($images as $img): 
            $src = pg_image_url($img['image_path']);
          ?>
            <div class="col-md-3">
              <div class="border rounded p-2 h-100">
                <img src="<?php echo htmlspecialchars($src); ?>" class="img-fluid rounded mb-2" alt="pg">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?php echo (int)$img['id']; ?>" id="del<?php echo (int)$img['id']; ?>">
                  <label class="form-check-label small" for="del<?php echo (int)$img['id']; ?>">Delete</label>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <button class="btn btn-primary">Save changes</button>
  </form>
</div>

<?php require_once '../includes/footer.php'; ?>
