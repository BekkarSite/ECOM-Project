<!-- public/add_to_cart.php -->
<?php
session_start();
include('../includes/db.php');

if (isset($_GET['product_id']) && isset($_GET['quantity'])) {
    $product_id = $_GET['product_id'];
    $quantity = $_GET['quantity'];

    // Check if product is already in the cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity; // Update quantity if product is already in the cart
    } else {
        $_SESSION['cart'][$product_id] = $quantity; // Add new product to the cart
    }

    // Redirect to the cart page
    header('Location: cart.php');
}
?>
