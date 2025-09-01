<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

$registrationPaused = false;
$settingsStmt = $conn->prepare("SELECT value FROM settings WHERE name = 'registration_paused' LIMIT 1");
if ($settingsStmt) {
    $settingsStmt->execute();
    $settingsStmt->bind_result($regValue);
    if ($settingsStmt->fetch()) {
        $registrationPaused = $regValue === '1';
    }
    $settingsStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($registrationPaused) {
        $error = 'Registration is currently paused.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

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
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare('INSERT INTO users (email, password, role) VALUES (?, ?, "customer")');

                    if ($stmt) {
                        $stmt->bind_param('ss', $email, $hashed_password);
                        if ($stmt->execute()) {
                            // Automatically log the user in after successful registration
                            $_SESSION['user_id'] = $conn->insert_id;
                            $_SESSION['email'] = $email;
                            header('Location: index.php');
                            exit;
                        } else {
                            $error = 'Error: ' . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
                        }
                        $stmt->close();
                    } else {
                        $error = 'Database error.';
                    }
                }
                $checkStmt->close();
            } else {
                $error = 'Database error.';
            }
        }
    }
}
?>
<link rel="stylesheet" href="../assets/css/custom/registerstyle.css">
<main class="register-wrapper">
    <?php if ($registrationPaused): ?>
        <p class="error">Registration is currently paused.</p>
    <?php else: ?>
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
            <?php endif; ?>
            <button type="submit">Register</button>
        </form>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>