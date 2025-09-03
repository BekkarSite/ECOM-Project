<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

$info = null; $error = null;

// Ensure password_resets table exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY(token), INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        if ($stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1')) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            // Always respond with success message to avoid email enumeration
            $info = 'If an account with that email exists, a reset link has been generated.';
            if ($user) {
                $user_id = (int)$user['id'];
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                // Upsert old tokens for user
                $conn->query("DELETE FROM password_resets WHERE user_id = $user_id");
                if ($ins = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')) {
                    $ins->bind_param('iss', $user_id, $token, $expires);
                    $ins->execute();
                    $ins->close();
                }
                // In a real system, email the link. For now, show the link for testing.
                $_SESSION['reset_preview_link'] = 'reset_password.php?token=' . urlencode($token);
            }
        } else {
            $error = 'Database error.';
        }
    }
}
?>
<main class="container py-4">
    <h1 class="h3">Forgot Password</h1>
    <p class="text-muted">Enter your account email and we'll send you a link to reset your password.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-info"><?= htmlspecialchars($info) ?><?php if (!empty($_SESSION['reset_preview_link'])): ?><br><small>Test link: <a href="<?= htmlspecialchars($_SESSION['reset_preview_link']) ?>">Reset Password</a></small><?php unset($_SESSION['reset_preview_link']); endif; ?></div><?php endif; ?>
    <form method="post" class="card card-body">
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="btn btn-primary">Send Reset Link</button>
        <p class="mt-3">Remembered your password? <a href="login.php">Back to login</a></p>
    </form>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
