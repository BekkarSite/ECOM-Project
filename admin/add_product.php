<?php
// admin/add_product.php (redesigned)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// CSRF token (shared approach with other admin pages)
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

$message = '';
$errors = [];

// Preserve input on validation errors
$old = [
    'name' => '',
    'description' => '',
    'price' => '',
    'category_id' => '',
    'stock' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!hash_equals($_SESSION['admin_csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $old['name'] = trim($_POST['name'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    if ($old['name'] === '') { $errors[] = 'Product name is required.'; }
    if ($old['description'] === '') { $errors[] = 'Description is required.'; }
    if ($price === false || $price < 0) { $errors[] = 'Please enter a valid non-negative price.'; }
    if ($category_id === false || $category_id <= 0) { $errors[] = 'Select a valid category.'; }
    if ($stock === false || $stock < 0) { $errors[] = 'Stock must be zero or a positive integer.'; }

    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['image']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed_exts, true) && @getimagesize($tmp_name)) {
            $image = uniqid('prod_', true) . '.' . $ext;
            $target_dir = __DIR__ . '/../assets/images/';
            $target_file = $target_dir . $image;
            if (!@move_uploaded_file($tmp_name, $target_file)) {
                $errors[] = 'Failed to upload image.';
            }
        } else {
            $errors[] = 'Invalid image format. Allowed: JPG, JPEG, PNG, GIF.';
        }
    } else {
        $errors[] = 'Product image is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO products (name, description, price, category_id, image, stock) VALUES (?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('ssdisi', $old['name'], $old['description'], $price, $category_id, $image, $stock);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = 'Product added successfully!';
                header('Location: manage_products.php');
                exit();
            } else {
                $message = 'Error adding product.';
            }
            $stmt->close();
        } else {
            $message = 'Failed to prepare statement.';
        }
    } else {
        $message = implode(' ', $errors);
        // Persist selected values
        $old['price'] = $price !== false ? (string)$price : '';
        $old['category_id'] = $category_id !== false ? (string)$category_id : '';
        $old['stock'] = $stock !== false ? (string)$stock : '';
    }
}

// Fetch categories
$categories = [];
if ($res = $conn->query('SELECT id, name FROM categories ORDER BY name ASC')) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Add Product</h1>
            <div class="d-flex gap-2">
                <a href="manage_products.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i> Back to Products</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="card card-body admin-form" style="max-width: 900px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
            <div class="mb-3">
                <label class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($old['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" step="0.01" name="price" min="0" class="form-control" value="<?= htmlspecialchars($old['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $row): ?>
                            <option value="<?= (int)$row['id']; ?>" <?= ((string)$row['id'] === (string)$old['category_id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Stock</label>
                    <input type="number" name="stock" min="0" class="form-control" value="<?= htmlspecialchars($old['stock'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Image (800 × 800 recommended)</label>
                <input type="file" name="image" id="image" class="form-control" accept="image/*" required>
                <small class="text-muted">After selecting an image, its dimensions will be shown below.</small>
                <div id="image-dimensions" class="mt-1 text-muted"></div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-admin-primary"><i class="fa fa-plus me-1"></i> Create Product</button>
                <button type="reset" class="btn btn-outline-secondary">Clear</button>
            </div>
        </form>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
<script>
  (function(){
    var imageInput = document.getElementById('image');
    var imageDimensions = document.getElementById('image-dimensions');
    if (!imageInput) return;
    imageInput.addEventListener('change', function(){
      var file = this.files && this.files[0];
      if (!file) { imageDimensions.textContent = ''; return; }
      var img = new Image();
      img.onload = function(){ imageDimensions.textContent = 'Image size: ' + this.naturalWidth + ' × ' + this.naturalHeight + ' px'; };
      img.src = URL.createObjectURL(file);
    });
  })();
</script>
</body>
</html>
