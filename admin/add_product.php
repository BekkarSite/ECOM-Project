<!-- admin/add_product.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
include('../includes/db.php');

// Handle adding product
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $image = $_FILES['image']['name'];
    $stock = $_POST['stock'];

    $target_dir = "../assets/images/";
    $target_file = $target_dir . basename($image);
    move_uploaded_file($_FILES['image']['tmp_name'], $target_file);

    $sql = "INSERT INTO products (name, description, price, category_id, image, stock)
            VALUES ('$name', '$description', '$price', '$category_id', '$image', '$stock')";
    if ($conn->query($sql) === TRUE) {
        echo "Product added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Add Product</h2>
<form method="POST" enctype="multipart/form-data">
    <label>Product Name:</label><br>
    <input type="text" name="name" required><br>
    <label>Description:</label><br>
    <textarea name="description" required></textarea><br>
    <label>Price:</label><br>
    <input type="number" name="price" required><br>
    <label>Category:</label><br>
    <select name="category_id" required>
        <?php
        $categories = $conn->query("SELECT * FROM categories");
        while ($row = $categories->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select><br>
    <label>Image:</label><br>
    <input type="file" name="image" required><br>
    <label>Stock:</label><br>
    <input type="number" name="stock" required><br>
    <button type="submit">Add Product</button>
</form>