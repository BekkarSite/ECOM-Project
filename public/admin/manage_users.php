<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../../config/db.php';

// Handle registration toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_registration'])) {
    $value = isset($_POST['registration_paused']) ? '1' : '0';
    $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE name = 'registration_paused'");
    if ($stmt) {
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch current registration status
$registrationPaused = false;
$result = $conn->query("SELECT value FROM settings WHERE name = 'registration_paused' LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $registrationPaused = $row['value'] === '1';
}

// Fetch users
$users = [];
$result = $conn->query("SELECT id, email, role, is_banned, created_at FROM users ORDER BY id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
</head>

<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <h1>Manage Users</h1>
            <form method="POST">
                <input type="hidden" name="toggle_registration" value="1">
                <label>
                    <input type="checkbox" name="registration_paused" value="1" <?php if ($registrationPaused) echo 'checked'; ?>>
                    Pause User Registration
                </label>
                <button type="submit">Save</button>
            </form>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= $user['is_banned'] ? 'Banned' : 'Active'; ?></td>
                            <td><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?= urlencode($user['id']); ?>">Edit</a>
                                <a href="toggle_user_status.php?id=<?= urlencode($user['id']); ?>&action=<?= $user['is_banned'] ? 'unban' : 'ban'; ?>" onclick="return confirm('Are you sure?');">
                                    <?= $user['is_banned'] ? 'Unban' : 'Ban'; ?>
                                </a>
                                <a href="delete_user.php?id=<?= urlencode($user['id']); ?>" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>

</html>