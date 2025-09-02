<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// CSRF helper
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

function set_flash($ok, $msg) {
    if ($ok) $_SESSION['flash_success'] = $msg; else $_SESSION['flash_error'] = $msg;
}

// Load user
$user = null;
if ($stmt = $conn->prepare('SELECT id, email, password, role, is_banned, created_at FROM users WHERE id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

if (!$user) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: login.php');
    exit();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        set_flash(false, 'Invalid CSRF token.');
        header('Location: profile.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash(false, 'Please enter a valid email address.');
        } else {
            if (strcasecmp($email, $user['email']) === 0) {
                set_flash(true, 'Profile updated.');
            } else {
                // Check uniqueness
                if ($chk = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1')) {
                    $chk->bind_param('si', $email, $user_id);
                    $chk->execute();
                    $exists = $chk->get_result()->fetch_assoc();
                    $chk->close();
                    if ($exists) {
                        set_flash(false, 'Email is already in use by another account.');
                    } else {
                        if ($upd = $conn->prepare('UPDATE users SET email = ? WHERE id = ?')) {
                            $upd->bind_param('si', $email, $user_id);
                            if ($upd->execute()) {
                                $_SESSION['email'] = $email; // keep session in sync
                                set_flash(true, 'Profile updated successfully.');
                            } else {
                                set_flash(false, 'Failed to update profile.');
                            }
                            $upd->close();
                        }
                    }
                }
            }
        }
        header('Location: profile.php');
        exit();
    }

    if ($action === 'update_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            set_flash(false, 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            set_flash(false, 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            set_flash(false, 'New password and confirmation do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            if ($upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?')) {
                $upd->bind_param('si', $hash, $user_id);
                if ($upd->execute()) {
                    set_flash(true, 'Password updated successfully.');
                } else {
                    set_flash(false, 'Failed to update password.');
                }
                $upd->close();
            }
        }
        header('Location: profile.php');
        exit();
    }

    if ($action === 'deactivate_account') {
        if ($upd = $conn->prepare('UPDATE users SET is_banned = 1 WHERE id = ?')) {
            $upd->bind_param('i', $user_id);
            $upd->execute();
            $upd->close();
        }
        // Log user out
        session_regenerate_id(true);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        header('Location: login.php');
        exit();
    }
}
?>

<main class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0">Profile & Settings</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i> Back to Dashboard</a>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Account Info</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($user['role'])); ?>" disabled>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Change Password</h2>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="update_password">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm new password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-key me-1"></i> Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <h2 class="h5 mb-0">Deactivate Account</h2>
                <form method="post" onsubmit="return confirm('Are you sure you want to deactivate your account? This action will log you out.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="deactivate_account">
                    <button type="submit" class="btn btn-outline-danger"><i class="fa fa-user-slash me-1"></i> Deactivate</button>
                </form>
            </div>
            <div class="card-body">
                <p class="mb-0 text-muted">Deactivating your account will prevent further logins. You can contact support to reactivate your account.</p>
            </div>
        </div>
    </div>
</main>

<script>
// Client-side Bootstrap validation (optional)
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
