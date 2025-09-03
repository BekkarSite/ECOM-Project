<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

$error = null; $success = null;
$token = $_GET['token'] ?? '';

if ($token === '') {
    $error = 'Invalid reset link.';
} else {
    // Find token
    if ($stmt = $conn->prepare('SELECT pr.user_id, pr.expires_at, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token = ? LIMIT 1')) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            $error = 'Invalid or expired reset token.';
        } elseif (strtotime($row['expires_at']) < time()) {
            $error = 'This reset token has expired. Please request another.';
        } else {
            $user_id = (int)$row['user_id'];
            $email = $row['email'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $new = trim($_POST['new_password'] ?? '');
                $confirm = trim($_POST['confirm_password'] ?? '');
                if (!is_strong_password($new, $pwErr)) {
                    $error = $pwErr ?? 'Password does not meet complexity requirements.';
                } elseif ($new !== $confirm) {
                    $error = 'Passwords do not match.';
                } else {
                    $hash = password_hash($new, PASSWORD_BCRYPT);
                    if ($upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?')) {
                        $upd->bind_param('si', $hash, $user_id);
                        if ($upd->execute()) {
                            $success = 'Your password has been reset. You can now log in.';
                            // Invalidate token after successful reset
                            $conn->query('DELETE FROM password_resets WHERE token = \'' . $conn->real_escape_string($token) . '\'' );
                        } else {
                            $error = 'Failed to update password.';
                        }
                        $upd->close();
                    } else {
                        $error = 'Database error.';
                    }
                }
            }
        }
    } else {
        $error = 'Database error.';
    }
}
?>
<main class="container py-4">
    <h1 class="h3">Reset Password</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="login.php">Go to login</a>.</div><?php endif; ?>

    <?php if (!$success && !$error || ($error && isset($row) && $row)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted">Resetting password for: <strong><?= isset($email) ? htmlspecialchars($email) : '' ?></strong></p>
            <form method="post">
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="10" required>
                    <div class="form-text">At least 10 chars, with upper, lower, digit, and special character.</div>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="10" required>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
