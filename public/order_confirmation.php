<!-- public/order_confirmation.php -->
<?php
session_start();
include('../includes/db.php');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];

    // Fetch order details
    $sql = "SELECT * FROM orders WHERE id = $order_id";
    $order = $conn->query($sql)->fetch_assoc();
    if (!$order) {
        echo "Order not found!";
        exit();
    }

    // Fetch order items
    $sql = "SELECT * FROM order_details WHERE order_id = $order_id";
    $order_details = $conn->query($sql);
} else {
    echo "Invalid order ID!";
    exit();
}
?>

<h2>Order Confirmation</h2>
<p>Thank you for your order! Here are the details:</p>

<h3>Order Summary</h3>
<ul>
    <?php while ($item = $order_details->fetch_assoc()) {
        $sql = "SELECT * FROM products WHERE id = " . $item['product_id'];
        $product = $conn->query($sql)->fetch_assoc();
        echo "<li>{$product['name']} - Quantity: {$item['quantity']} - Price: {$item['price']}</li>";
    } ?>
</ul>

<h3>Total Price: <?php echo $order['total_price']; ?> USD</h3>
<p>Status: <?php echo ucfirst($order['status']); ?></p>
