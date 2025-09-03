<!-- admin/manage_categories.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
include('../config/db.php');

// Handle adding category
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        $message = 'Category added successfully!';
    } else {
        $message = 'Error: ' . $conn->error;
    }
    $stmt->close();
}

// Fetch categories
$categories = $conn->query("SELECT id, name FROM categories");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>

<body>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
    <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h2>Manage Categories</h2>
            <?php if (!empty($message)): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="POST">
                <label>Category Name:</label><br>
                <input type="text" name="name" required><br>
                <button type="submit">Add Category</button>
            </form>

            <h3>Existing Categories</h3>
            <ul>
                <?php while ($row = $categories->fetch_assoc()): ?>
                    <li>
                        <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <a href="edit_category.php?id=<?= $row['id']; ?>">Edit</a>
                        <a href="delete_category.php?id=<?= $row['id']; ?>" onclick="return confirm('Delete this category?');">Delete</a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </main>
    </div>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>

</html>
