<!-- admin/manage_categories.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
include('../includes/db.php');

// Handle adding category
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        echo "Category added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
    $stmt->close();
}

// Fetch categories
$categories = $conn->query("SELECT id, name FROM categories");
?>

<h2>Manage Categories</h2>
<form method="POST">
    <label>Category Name:</label><br>
    <input type="text" name="name" required><br>
    <button type="submit">Add Category</button>
</form>

<h3>Existing Categories</h3>
<ul>
<?php while ($row = $categories->fetch_assoc()): ?>
    <li><?= htmlspecialchars($row['name']) ?></li>
<?php endwhile; ?>
</ul>