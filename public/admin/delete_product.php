<!-- admin/delete_product.php -->
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

include('../../config/db.php');

if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $sql = "DELETE FROM products WHERE id = $product_id";
    if ($conn->query($sql) === TRUE) {
        header('Location: manage_products.php');
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
