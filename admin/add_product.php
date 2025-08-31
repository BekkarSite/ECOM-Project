<!-- admin/add_product.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once '../config/db.php';

$message = '';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $image = '';

    if ($price === false || $price < 0) {
        $errors[] = 'Please enter a valid price.';
    }
    if ($stock === false || $stock < 0) {
        $errors[] = 'Stock must be zero or a positive integer.';
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['image']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed_exts) && getimagesize($tmp_name)) {
            $image = uniqid('prod_', true) . '.' . $ext;
            $target_dir = '../assets/images/';
            $target_file = $target_dir . $image;
            if (!move_uploaded_file($tmp_name, $target_file)) {
                $errors[] = 'Failed to upload image.';
            }
        } else {
            $errors[] = 'Invalid image format.';
        }
    } else {
        $errors[] = 'Image is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            'INSERT INTO products (name, description, price, category_id, image, stock) VALUES (?, ?, ?, ?, ?, ?)'
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
    } else {
        $message = implode(' ', $errors);
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
    <link rel="stylesheet" href="../assets/css/addproductstyle.css">
</head>

<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <div class="add-product-wrapper">
                <h2>Add Product</h2>
                <?php if (!empty($message)): ?>
                    <p class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <label>Product Name:</label>
                    <input type="text" name="name" required>
                    <label>Description:</label>
                    <textarea name="description" required></textarea>
                    <label>Price:</label>
                    <input type="number" step="0.01" name="price" min="0" required>
                    <label>Category:</label>
                    <select name="category_id" required>
                        <?php while ($row = $categories->fetch_assoc()): ?>
                            <option value="<?= $row['id']; ?>"><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <label>Image:</label>
                    <input type="file" name="image" required>
                    <label>Stock:</label>
                    <input type="number" name="stock" min="0" required>
                    <button type="submit">Add Product</button>
                </form>
            </div>
        </main>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>