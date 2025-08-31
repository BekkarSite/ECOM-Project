<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: manage_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] === 'admin' ? 'admin' : 'customer';
    $stmt = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $role, $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: manage_users.php');
    exit();
}

$stmt = $conn->prepare('SELECT email, role FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($email, $role);
    if (!$stmt->fetch()) {
        $stmt->close();
        header('Location: manage_users.php');
        exit();
    }
    $stmt->close();
} else {
    header('Location: manage_users.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
</head>

<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <h1>Edit User</h1>
            <form method="POST">
                <p>Email: <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="customer" <?php if ($role === 'customer') echo 'selected'; ?>>Customer</option>
                    <option value="admin" <?php if ($role === 'admin') echo 'selected'; ?>>Admin</option>
                </select>
                <button type="submit">Update</button>
            </form>
        </main>
    </div>
</body>

</html>