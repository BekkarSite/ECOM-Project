<!-- public/checkout.php -->
<?php
session_start();
include('../includes/db.php');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login if not logged in
    exit();
}

// Fetch the user's cart
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (count($cart) == 0) {
    echo "Your cart is empty!";
    exit();
}

// Calculate total price of items in the cart
$total_price = 0;
foreach ($cart as $product_id => $quantity) {
    $sql = "SELECT * FROM products WHERE id = $product_id";
    $product = $conn->query($sql)->fetch_assoc();
    $total_price += $product['price'] * $quantity;
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $address = $_POST['address'];
    $payment_method = $_POST['payment_method'];

    // Insert order into the orders table
    $user_id = $_SESSION['user_id'];
    $sql = "INSERT INTO orders (user_id, total_price, status) VALUES ($user_id, $total_price, 'pending')";
    if ($conn->query($sql) === TRUE) {
        $order_id = $conn->insert_id;

        // Insert order details for each product in the cart
        foreach ($cart as $product_id => $quantity) {
            $sql = "SELECT * FROM products WHERE id = $product_id";
            $product = $conn->query($sql)->fetch_assoc();
            $price = $product['price'];

            // Insert into order_details table
            $sql = "INSERT INTO order_details (order_id, product_id, quantity, price) VALUES ($order_id, $product_id, $quantity, $price)";
            $conn->query($sql);
        }

        // Clear the cart after order placement
        unset($_SESSION['cart']);

        // Send user to order confirmation page (or payment gateway)
        header("Location: order_confirmation.php?order_id=$order_id");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<h2>Checkout</h2>
<h3>Your Cart:</h3>
<ul>
    <?php
    foreach ($cart as $product_id => $quantity) {
        $sql = "SELECT * FROM products WHERE id = $product_id";
        $product = $conn->query($sql)->fetch_assoc();
        echo "<li>{$product['name']} - Quantity: $quantity - Price: {$product['price']}</li>";
    }
    ?>
</ul>
<h3>Total Price: <?php echo $total_price; ?> PKR</h3>

<h3>Shipping Information</h3>
<form method="POST">
    <label>Shipping Address:</label><br>
    <textarea name="address" required></textarea><br>
    <label>Payment Method:</label><br>
    <select name="payment_method" required>
        <option value="credit_card">Credit Card</option>
        <option value="paypal">PayPal</option>
    </select><br>
    <button type="submit">Place Order</button>
</form>
