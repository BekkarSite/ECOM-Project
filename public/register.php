<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } else {
        // Check if the email is already registered to avoid duplicate entries
        $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        if ($checkStmt) {
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $error = 'Email already registered!';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO users (email, password, role) VALUES (?, ?, "customer")');

                if ($stmt) {
                    $stmt->bind_param('ss', $email, $hashed_password);
                    if ($stmt->execute()) {
                        $success = 'Registration successful! <a href="login.php">Login</a>.';
                    } else {
                        $error = 'Error: ' . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
                    }
                    $stmt->close();
                } else {
                    $error = 'Database error.';
                }
            }
            $stmt->close();
        } else {
            $error = 'Database error.';
        }
    }
}
?>
<link rel="stylesheet" href="../assets/css/registerstyle.css">
<main class="register-wrapper">
    <form method="POST" class="register-form">
        <h2>Register</h2>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (isset($success)): ?>
            <p class="success"><?= $success; ?></p>
        <?php endif; ?>
        <button type="submit">Register</button>
    </form>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>