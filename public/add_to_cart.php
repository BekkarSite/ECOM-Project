<!-- public/add_to_cart.php -->
<?php
session_start();

// Ensure both product_id and quantity are provided
if (isset($_GET['product_id'], $_GET['quantity'])) {
    // Cast incoming values to integers to avoid unexpected input
    $product_id = (int) $_GET['product_id'];
    $quantity = max(1, (int) $_GET['quantity']);

    // Initialize the cart if it doesn't exist yet
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Check if product is already in the cart and update accordingly
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }

    // Determine if the request was made via AJAX
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        // Return the new cart count as JSON
        header('Content-Type: application/json');
        echo json_encode(['count' => array_sum($_SESSION['cart'])]);
    } else {
        // Redirect to the cart page for normal requests
        header('Location: cart.php');
    }
    exit();
}
?>