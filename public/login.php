<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers/security.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

// Prepare next redirect parameter if provided
$next = isset($_GET['next']) ? $_GET['next'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $captcha = $_POST['captcha'] ?? '';

    if (!captcha_validate($captcha)) {
        $error = 'CAPTCHA verification failed.';
    } else {
        $stmt = $conn->prepare('SELECT id, email, password, is_banned FROM users WHERE email = ?');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if ($user['is_banned']) {
                    $error = 'Account is banned.';
                } elseif (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $redirect = isset($_POST['next']) && $_POST['next'] !== '' ? $_POST['next'] : 'index.php';
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Invalid credentials!';
                }
            } else {
                $error = 'No user found with that email!';
            }
            $stmt->close();
        } else {
            $error = 'Database error.';
        }
    }
}
// Always generate a new CAPTCHA challenge when showing the form
$captchaQuestion = captcha_generate();
?>
<link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/custom/loginstyle.css">

<main class="login-wrapper">
    <form method="POST" class="login-form">
        <h2>Login</h2>
        <p class="hint">Enter your email and password to access your account. If you don't have an account, you can <a href="register.php<?= $next ? ('?next=' . urlencode($next)) : '' ?>">register here</a>. Forgotten your password? <a href="forgot_password.php">Reset it</a>.</p>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="you@example.com" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <label for="captcha"><?= htmlspecialchars($captchaQuestion, ENT_QUOTES, 'UTF-8'); ?></label>
        <input type="text" id="captcha" name="captcha" placeholder="Answer" required>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <button type="submit">Login</button>
        <p class="alt-link">New here? <a href="register.php<?= $next ? ('?next=' . urlencode($next)) : '' ?>">Create an account</a></p>
    </form>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
