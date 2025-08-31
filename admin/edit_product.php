<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
include('../config/db.php');

if (isset($_GET['id'])) {
    $product_id = (int) $_GET['id'];
    $product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
} else {
    header('Location: manage_products.php');
    exit();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $image = $_FILES['image']['name'];
    $stock = $_POST['stock'];

    if ($image) {
        $target_dir = "../../assets/images/";
        $target_file = $target_dir . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
        $image_sql = ", image='$image'";
    } else {
        $image_sql = "";
    }

    $sql = "UPDATE products SET name='$name', description='$description', price='$price',
                category_id='$category_id', stock='$stock' $image_sql WHERE id=$product_id";
    if ($conn->query($sql) === TRUE) {
        $message = 'Product updated successfully!';
        $product = $conn->query("SELECT * FROM products WHERE id = $product_id")->fetch_assoc();
    } else {
        $message = 'Error: ' . $conn->error;
    }
}

$categories = $conn->query("SELECT id, name FROM categories");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/manageproductsstyle.css">
</head>

<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h2>Edit Product</h2>
            <?php if (!empty($message)): ?>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <label>Product Name:</label><br>
                <input type="text" name="name" value="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required><br>
                <label>Description:</label><br>
                <textarea name="description" required><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></textarea><br>
                <label>Price:</label><br>
                <input type="number" name="price" value="<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8'); ?>" required><br>
                <label>Category:</label><br>
                <select name="category_id" required>
                    <?php while ($row = $categories->fetch_assoc()): ?>
                        <option value="<?= $row['id']; ?>" <?= $row['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endwhile; ?>
                </select><br>
                <label>Image:</label><br>
                <input type="file" name="image"><br>
                <label>Stock:</label><br>
                <input type="number" name="stock" value="<?= htmlspecialchars($product['stock'], ENT_QUOTES, 'UTF-8'); ?>" required><br>
                <button type="submit">Update Product</button>
            </form>
        </main>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>