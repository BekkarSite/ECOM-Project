<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// Fetch available roles
$roles = [];
$result = $conn->query('SELECT name FROM roles ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row['name'];
    }
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: manage_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    if (!in_array($role, $roles, true)) {
        $role = 'customer';
    }
    $is_banned = isset($_POST['is_banned']) ? 1 : 0;
    $password = trim($_POST['password'] ?? '');

    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET email = ?, role = ?, is_banned = ?, password = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ssisi', $email, $role, $is_banned, $hashed, $id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare('UPDATE users SET email = ?, role = ?, is_banned = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ssii', $email, $role, $is_banned, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: manage_users.php');
    exit();
}

$stmt = $conn->prepare('SELECT email, role, is_banned FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($email, $role, $is_banned);
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
    <?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
            <main class="content">
            <h1>Edit User</h1>
            <form method="POST">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>

                <label for="password">New Password (leave blank to keep current)</label>
                <input type="password" id="password" name="password">

                <label for="role">Role</label>
                <select id="role" name="role">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($role === $r) echo 'selected'; ?>>
                            <?= htmlspecialchars(ucfirst($r), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>
                    <input type="checkbox" name="is_banned" value="1" <?php if ($is_banned) echo 'checked'; ?>>
                    Banned
                </label>

                <button type="submit">Update</button>
            </form>
        </main>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>