<!-- admin/manage_products.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php'); // Redirect if not logged in
    exit();
}
include('../includes/db.php'); // Include the database connection file

// Handle adding a product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $image = $_FILES['image']['name'];
    $stock = $_POST['stock'];

    // Upload image
    $target_dir = "../assets/images/";
    $target_file = $target_dir . basename($image);
    move_uploaded_file($_FILES['image']['tmp_name'], $target_file);

    // Insert product into database
    $sql = "INSERT INTO products (name, description, price, category_id, image, stock)
            VALUES ('$name', '$description', '$price', '$category_id', '$image', '$stock')";
    if ($conn->query($sql) === TRUE) {
        echo "Product added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Fetch products to display
$sql = "SELECT * FROM products";
$products = $conn->query($sql);
?>

<h2>Manage Products</h2>

<form method="POST" enctype="multipart/form-data">
    <h3>Add Product</h3>
    <label>Product Name:</label><br>
    <input type="text" name="name" required><br>
    <label>Description:</label><br>
    <textarea name="description" required></textarea><br>
    <label>Price:</label><br>
    <input type="number" name="price" required><br>
    <label>Category:</label><br>
    <select name="category_id" required>
        <?php
        // Fetch categories for dropdown
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
    <button type="submit" name="add_product">Add Product</button>
</form>

<h3>Existing Products</h3>
<table>
    <tr>
        <th>Product Name</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Action</th>
    </tr>
    <?php
    while ($product = $products->fetch_assoc()) {
        echo "<tr>
                <td>{$product['name']}</td>
                <td>{$product['price']}</td>
                <td>{$product['stock']}</td>
                <td>
                    <a href='edit_product.php?id={$product['id']}'>Edit</a> | 
                    <a href='delete_product.php?id={$product['id']}'>Delete</a>
                </td>
              </tr>";
    }
    ?>
</table>
