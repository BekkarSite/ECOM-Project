<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// Handle add/edit role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['role_name'] ?? '');
    if ($name !== '') {
        if ($action === 'add') {
            $stmt = $conn->prepare('INSERT INTO roles (name) VALUES (?)');
            if ($stmt) {
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $stmt->close();
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['role_id'] ?? 0);
            // Get old role name
            $oldName = '';
            $stmtOld = $conn->prepare('SELECT name FROM roles WHERE id = ?');
            if ($stmtOld) {
                $stmtOld->bind_param('i', $id);
                $stmtOld->execute();
                $stmtOld->bind_result($oldName);
                $stmtOld->fetch();
                $stmtOld->close();
            }
            $stmt = $conn->prepare('UPDATE roles SET name = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $name, $id);
                $stmt->execute();
                $stmt->close();
            }
            if ($oldName !== '') {
                $stmt = $conn->prepare('UPDATE users SET role = ? WHERE role = ?');
                if ($stmt) {
                    $stmt->bind_param('ss', $name, $oldName);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

// Handle delete role
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $roleName = '';
    $stmt = $conn->prepare('SELECT name FROM roles WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($roleName);
        $stmt->fetch();
        $stmt->close();
    }
    if ($roleName !== '') {
        // Check if role is assigned to any user
        $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        if ($stmt) {
            $stmt->bind_param('s', $roleName);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
        }
        if ($count == 0) {
            $stmt = $conn->prepare('DELETE FROM roles WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

$roles = [];
$result = $conn->query('SELECT id, name FROM roles ORDER BY id ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Role Management</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
</head>

<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h1>User Role Management</h1>
            <section class="add-role">
                <h2>Add Role</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="role_name" required>
                    <button type="submit">Add</button>
                </form>
            </section>
            <section class="role-list">
                <h2>Existing Roles</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td><?= $role['id']; ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="role_id" value="<?= $role['id']; ?>">
                                        <input type="text" name="role_name" value="<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <button type="submit">Update</button>
                                    </form>
                                </td>
                                <td>
                                    <a href="manage_roles.php?delete=<?= $role['id']; ?>" onclick="return confirm('Delete this role?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>