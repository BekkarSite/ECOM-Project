<!-- public/cart.php -->
<?php
session_start();
include('../includes/db.php');

if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0) {
    // Get product details from the cart
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $sql = "SELECT * FROM products WHERE id = $product_id";
        $product = $conn->query($sql)->fetch_assoc();
        echo "<div>{$product['name']} - Quantity: $quantity - Price: {$product['price']}</div>";
    }
} else {
    echo "Your cart is empty.";
}
?>
