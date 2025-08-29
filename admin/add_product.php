<!-- admin/add_product.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once '../includes/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float) $_POST['price'];
    $category_id = (int) $_POST['category_id'];
    $stock = (int) $_POST['stock'];
    $image = '';

    if (!empty($_FILES['image']['name'])) {
        $image = basename($_FILES['image']['name']);
        $target_dir = "../assets/images/";
        $target_file = $target_dir . $image;
        move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
    }

    $stmt = $conn->prepare(
        'INSERT INTO products (name, description, price, category_id, image, stock)'
            . ' VALUES (?, ?, ?, ?, ?, ?)'
    );

    if ($stmt) {
        $stmt->bind_param('ssdisi', $name, $description, $price, $category_id, $image, $stock);
        if ($stmt->execute()) {
            $message = 'Product added successfully!';
        } else {
            $message = 'Error adding product.';
        }
        $stmt->close();
    } else {
        $message = 'Failed to prepare statement.';
    }
}

$categories = $conn->query('SELECT id, name FROM categories');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/manageproductsstyle.css">
</head>

<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <h2>Add Product</h2>
            <?php if (!empty($message)): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Product Name:</label><br>
                <input type="text" name="name" required><br>
                <label>Description:</label><br>
                <textarea name="description" required></textarea><br>
                <label>Price:</label><br>
                <input type="number" step="0.01" name="price" required><br>
                <label>Category:</label><br>
                <select name="category_id" required>
                    <?php while ($row = $categories->fetch_assoc()): ?>
                        <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endwhile; ?>
                </select><br>
                <label>Image:</label><br>
                <input type="file" name="image" required><br>
                <label>Stock:</label><br>
                <input type="number" name="stock" required><br>
                <button type="submit">Add Product</button>
            </form>
        </main>
    </div>
</body>

</html>