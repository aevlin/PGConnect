<?php
// backend/login.php
// Start session early so header redirects and session writes work reliably
if (session_status() === PHP_SESSION_NONE) {
    @session_set_cookie_params(0, '/');
    session_start();
}

// Define BASE_URL for safe absolute redirects (keeps behavior aligned with includes/header.php)
if (!defined('BASE_URL')) define('BASE_URL', '/PGConnect');

require_once 'connect.php'; // PDO connection
require_once 'user_schema.php';

$error = '';
ensure_user_profile_schema($pdo);

// Log helper
function backend_log($msg) {
    @file_put_contents(__DIR__ . '/login.log', date('Y-m-d H:i:s') . " " . $msg . "\n", FILE_APPEND);
}

function owner_can_skip_onboarding(array $user): bool {
    return (($user['role'] ?? '') === 'owner')
        && strtolower((string)($user['owner_verification_status'] ?? '')) === 'approved';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Debug: log attempt (lightweight)
    backend_log("LOGIN_ATTEMPT: {$email}");

    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (owner_can_skip_onboarding($user) && (int)($user['onboarding_completed'] ?? 0) !== 1) {
                try {
                    $mark = $pdo->prepare('UPDATE users SET onboarding_completed = 1 WHERE id = ?');
                    $mark->execute([(int)$user['id']]);
                    $user['onboarding_completed'] = 1;
                } catch (Throwable $e) {
                    backend_log('ONBOARDING_SKIP_MARK_ERROR: ' . $e->getMessage());
                }
            }

            // SET ALL SESSION DATA
            // regenerate session id on successful login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            if (($user['role'] ?? '') !== 'admin' && (int)($user['onboarding_completed'] ?? 0) !== 1) {
                header('Location: ' . BASE_URL . '/backend/onboarding.php');
                exit;
            }

            // ROLE-BASED REDIRECTS (use absolute path to avoid relative path mistakes)
            if ($user['role'] === 'owner') {
                header('Location: ' . BASE_URL . '/owner/owner-dashboard.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: ' . BASE_URL . '/admin/admin-dashboard.php');
            } else {
                header('Location: ' . BASE_URL . '/user/user-profile.php');
            }
            exit;
        } else {
            // Fallback for legacy plaintext passwords: accept and migrate to hashed password
            if ($user && isset($user['password']) && $user['password'] === $password) {
                try {
                    // migrate to hashed password
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $upd->execute([$newHash, $user['id']]);
                    if (owner_can_skip_onboarding($user) && (int)($user['onboarding_completed'] ?? 0) !== 1) {
                        $mark = $pdo->prepare('UPDATE users SET onboarding_completed = 1 WHERE id = ?');
                        $mark->execute([(int)$user['id']]);
                        $user['onboarding_completed'] = 1;
                    }
                    // set session
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    if (($user['role'] ?? '') !== 'admin' && (int)($user['onboarding_completed'] ?? 0) !== 1) {
                        header('Location: ' . BASE_URL . '/backend/onboarding.php');
                        exit;
                    }
                    // redirect
                    if ($user['role'] === 'owner') {
                        header('Location: ' . BASE_URL . '/owner/owner-dashboard.php');
                    } elseif ($user['role'] === 'admin') {
                        header('Location: ' . BASE_URL . '/admin/admin-dashboard.php');
                    } else {
                        header('Location: ' . BASE_URL . '/user/user-profile.php');
                    }
                    exit;
                } catch (Exception $e) {
                    backend_log('MIGRATE_ERROR: ' . $e->getMessage());
                }
            }

            $error = "Invalid email/password";
            backend_log("LOGIN_FAILED: {$email}");
        }
    } catch (Exception $e) {
        // Log DB errors for debugging, show friendly message
        backend_log('ERROR: ' . $e->getMessage());
        $error = 'Login failed due to a server error. Please try again later.';
    }
}

// Render login card inside the standard header layout
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
                            <h2 class="hero-title mt-2 mb-1" style="font-size:1.4rem;">Sign in to your account</h2>
                            <p class="small text-muted mb-0">Welcome back — enter your credentials below.</p>
                        </div>

                        <?php if($error): ?>
                            <div class="alert alert-danger rounded-2 mb-3">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . '/backend/login.php'); ?>">
                            <div class="mb-3">
                                <label class="form-label small mb-1">Email</label>
                                <input type="email" name="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="you@company.com" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small mb-1">Password</label>
                                <input type="password" name="password" class="form-control form-control-lg" placeholder="••••••••" required>
                            </div>

                            <button type="submit" class="btn btn-gradient w-100 py-2 fw-semibold mb-3">Sign in</button>
                        </form>

                        <div class="text-center mt-2">
                            <p class="small mb-0">Don't have an account? <a href="<?php echo htmlspecialchars(BASE_URL . '/backend/signup.php'); ?>" class="fw-semibold">Create one</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
