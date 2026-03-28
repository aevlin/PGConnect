<?php
require_once __DIR__ . '/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    @session_set_cookie_params(0, '/');
    session_start();
}

require_once 'connect.php';
require_once 'user_schema.php';
ensure_user_profile_schema($pdo);

$error = '';
$success = '';

function signup_log($msg) {
    @file_put_contents(__DIR__ . '/signup.log', date('Y-m-d H:i:s') . " " . $msg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = trim($_POST['role'] ?? 'user');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                $allowed_roles = ['user', 'owner'];
                if (!in_array($role, $allowed_roles, true)) {
                    $role = 'user';
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, onboarding_completed, created_at) VALUES (?, ?, ?, ?, 0, NOW())');

                if ($stmt->execute([$name, $email, $hashed_password, $role])) {
                    $success = 'Account created! Please login.';
                    signup_log("NEW_USER: {$email} role={$role}");
                } else {
                    $error = 'Signup failed. Try again.';
                    signup_log("FAILED_INSERT: {$email} role={$role}");
                }
            }
        } catch (Exception $e) {
            $error = 'Server error during signup. Try again later.';
            signup_log('ERROR: ' . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<section class="section-shell">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <div class="eyebrow"><span></span> PGConnect</div>
                            <h2 class="hero-title mt-2 mb-1" style="font-size:1.4rem;">Create your account</h2>
                            <p class="small text-muted mb-0">Sign up to list or book PGs</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success mb-3"><?php echo htmlspecialchars($success); ?></div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-gradient w-100 py-2">Go to Login</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="mb-3">
                                    <label class="form-label small mb-1">Full name</label>
                                    <input type="text" name="name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small mb-1">Email</label>
                                    <input type="email" name="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small mb-1">Password</label>
                                    <input type="password" name="password" class="form-control form-control-lg" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small mb-1">Confirm password</label>
                                    <input type="password" name="confirm_password" class="form-control form-control-lg" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small mb-2">Sign up as</label>
                                    <?php $roleValue = htmlspecialchars($_POST['role'] ?? 'user'); ?>
                                    <div class="d-flex gap-3">
                                        <label class="role-tile">
                                            <input type="radio" name="role" value="user" <?php echo ($roleValue === 'user') ? 'checked' : ''; ?>>
                                            <span class="tile-body">
                                                <i class="fa-solid fa-user"></i>
                                                <span>Tenant</span>
                                            </span>
                                        </label>
                                        <label class="role-tile">
                                            <input type="radio" name="role" value="owner" <?php echo ($roleValue === 'owner') ? 'checked' : ''; ?>>
                                            <span class="tile-body">
                                                <i class="fa-solid fa-house"></i>
                                                <span>Owner</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-gradient w-100 py-2 mb-2">Create account</button>
                            </form>

                            <div class="text-center mt-2">
                                <p class="small mb-0">Already have an account? <a href="login.php" class="fw-semibold">Sign in</a></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
