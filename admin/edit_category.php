<?php
// public/admin/edit_category.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once '../config/db.php';

if (!isset($_GET['id'])) {
    header('Location: manage_categories.php');
    exit();
}

$category_id = (int) $_GET['id'];

// Fetch category details
$stmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
$stmt->bind_param('i', $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    header('Location: manage_categories.php');
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $update = $conn->prepare('UPDATE categories SET name = ? WHERE id = ?');
    $update->bind_param('si', $name, $category_id);
    if ($update->execute()) {
        $message = 'Category updated successfully!';
        $category['name'] = $name;
    } else {
        $message = 'Error: ' . $conn->error;
    }
    $update->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Category</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>

<body>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
    <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h2>Edit Category</h2>
            <?php if (!empty($message)): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="POST">
                <label>Category Name:</label><br>
                <input type="text" name="name" value="<?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>" required><br>
                <button type="submit">Update Category</button>
            </form>
        </main>
    </div>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>

</html>
