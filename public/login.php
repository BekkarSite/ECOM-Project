<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare('SELECT id, email, password FROM users WHERE email = ?');
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                header('Location: index.php');
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
?>
<link rel="stylesheet" href="../assets/css/loginstyle.css">

<main class="login-wrapper">
    <form method="POST" class="login-form">
        <h2>Login</h2>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <button type="submit">Login</button>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>