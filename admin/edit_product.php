<!-- admin/edit_product.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php'); // Redirect if not logged in
    exit();
}
include('../includes/db.php'); // Include the database connection file

// Fetch product to edit
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $sql = "SELECT * FROM products WHERE id = $product_id";
    $product = $conn->query($sql)->fetch_assoc();
}

// Handle updating product
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $image = $_FILES['image']['name'];
    $stock = $_POST['stock'];

    // Upload image if exists
    if ($image) {
        $target_dir = "../assets/images/";
        $target_file = $target_dir . basename($image);
        move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
        $image_sql = ", image='$image'";
    } else {
        $image_sql = "";
    }

    // Update product
    $sql = "UPDATE products SET name='$name', description='$description', price='$price', 
            category_id='$category_id', stock='$stock' $image_sql WHERE id=$product_id";
    if ($conn->query($sql) === TRUE) {
        echo "Product updated successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Edit Product</h2>
<form method="POST" enctype="multipart/form-data">
    <label>Product Name:</label><br>
    <input type="text" name="name" value="<?php echo $product['name']; ?>" required><br>
    <label>Description:</label><br>
    <textarea name="description" required><?php echo $product['description']; ?></textarea><br>
    <label>Price:</label><br>
    <input type="number" name="price" value="<?php echo $product['price']; ?>" required><br>
    <label>Category:</label><br>
    <select name="category_id" required>
        <?php
        $categories = $conn->query("SELECT * FROM categories");
        while ($row = $categories->fetch_assoc()) {
            echo "<option value='{$row['id']}' " . ($row['id'] == $product['category_id'] ? 'selected' : '') . ">{$row['name']}</option>";
        }
        ?>
    </select><br>
    <label>Image:</label><br>
    <input type="file" name="image"><br>
    <label>Stock:</label><br>
    <input type="number" name="stock" value="<?php echo $product['stock']; ?>" required><br>
    <button type="submit">Update Product</button>
</form>
