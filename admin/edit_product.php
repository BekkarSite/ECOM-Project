<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) { header('Location: manage_products.php'); exit(); }

// Fetch categories
$categories = [];
if ($res = $conn->query('SELECT id, name FROM categories ORDER BY name ASC')) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
}

// Fetch product
$product = null;
if ($stmt = $conn->prepare('SELECT id, name, description, price, category_id, image, stock FROM products WHERE id = ?')) {
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $product = $res->fetch_assoc();
    $stmt->close();
}
if (!$product) { header('Location: manage_products.php'); exit(); }

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    if ($name === '') { $errors[] = 'Name is required.'; }
    if ($description === '') { $errors[] = 'Description is required.'; }
    if ($price === false || $price < 0) { $errors[] = 'Price must be a non-negative number.'; }
    if ($category_id === false || $category_id <= 0) { $errors[] = 'Select a valid category.'; }
    if ($stock === false || $stock < 0) { $errors[] = 'Stock must be zero or positive integer.'; }

    $newImage = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['image']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($ext, $allowed_exts) && @getimagesize($tmp_name)) {
                $newImage = uniqid('prod_', true) . '.' . $ext;
                $target_dir = __DIR__ . '/../assets/images/';
                if (!@move_uploaded_file($tmp_name, $target_dir . $newImage)) {
                    $errors[] = 'Failed to upload image.';
                    $newImage = null;
                }
            } else {
                $errors[] = 'Invalid image file.';
            }
        } else {
            $errors[] = 'Image upload error.';
        }
    }

    if (empty($errors)) {
        if ($newImage) {
            $sql = 'UPDATE products SET name=?, description=?, price=?, category_id=?, stock=?, image=? WHERE id=?';
        } else {
            $sql = 'UPDATE products SET name=?, description=?, price=?, category_id=?, stock=? WHERE id=?';
        }
        if ($stmt = $conn->prepare($sql)) {
            if ($newImage) {
                $stmt->bind_param('ssdisis', $name, $description, $price, $category_id, $stock, $newImage, $product_id);
            } else {
                $stmt->bind_param('ssdisi', $name, $description, $price, $category_id, $stock, $product_id);
            }
            if ($stmt->execute()) {
                $message = 'Product updated successfully!';
                // Refresh product
                if ($stmt2 = $conn->prepare('SELECT id, name, description, price, category_id, image, stock FROM products WHERE id = ?')) {
                    $stmt2->bind_param('i', $product_id);
                    $stmt2->execute();
                    $res2 = $stmt2->get_result();
                    $product = $res2->fetch_assoc();
                    $stmt2->close();
                }
            } else {
                $message = 'Error updating product.';
            }
            $stmt->close();
        } else {
            $message = 'Failed to prepare statement.';
        }
    } else {
        $message = implode(' ', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/custom/manageproductsstyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <h2>Edit Product</h2>
        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="card card-body" style="max-width: 800px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>" />
            <div class="mb-3">
                <label class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as $row): ?>
                            <option value="<?= (int)$row['id']; ?>" <?= (int)$row['id'] === (int)$product['category_id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Stock</label>
                    <input type="number" name="stock" class="form-control" min="0" value="<?= htmlspecialchars($product['stock'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Image</label>
                <input type="file" name="image" class="form-control">
                <?php if (!empty($product['image'])): ?>
                    <div class="mt-2">
                        <img src="../assets/images/<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Current image" style="width:100px;height:100px;object-fit:cover;border-radius:6px;border:1px solid #eee;" />
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-admin-primary">Update Product</button>
                <a href="manage_products.php" class="btn btn-outline-secondary">Back</a>
            </div>
        </form>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
