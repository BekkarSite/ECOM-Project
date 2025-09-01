<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch users
$users = [];
if ($search !== '') {
    $searchTerm = "%" . $search . "%";
    $searchId = (int) $search;
    $stmt = $conn->prepare("SELECT id, email, role, is_banned, created_at FROM users WHERE email LIKE ? OR id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('si', $searchTerm, $searchId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
    }
} else {
    $result = $conn->query("SELECT id, email, role, is_banned, created_at FROM users ORDER BY id ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/custom/manageusersstyle.css">
</head>

<body>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
    <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <div class="manage-users-wrapper">
                <h1>Manage Users</h1>
                <div class="top-actions">
                    <form method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search by email or ID" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit">Search</button>
                    </form>
                    <form method="POST" class="registration-form">
                        <input type="hidden" name="toggle_registration" value="1">
                        <label>
                            <input type="checkbox" name="registration_paused" value="1" <?php if ($registrationPaused) echo 'checked'; ?>>
                            Pause User Registration
                        </label>
                        <button type="submit">Save</button>
                    </form>
                </div>
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
            </div>
        </main>
    </div>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>

</html>