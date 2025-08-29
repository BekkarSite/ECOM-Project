<!-- public/order_confirmation.php -->
<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$order = null;
$order_items = null;
if (isset($_GET['order_id'])) {
    $order_id = (int) $_GET['order_id'];

    // Fetch order details for the logged in user
    $stmt = $conn->prepare('SELECT id, total_price, status FROM orders WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $order_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();

        // Fetch order items with product names
        $itemStmt = $conn->prepare('SELECT p.name, d.quantity, d.price FROM order_details d JOIN products p ON d.product_id = p.id WHERE d.order_id = ?');
        $itemStmt->bind_param('i', $order_id);
        $itemStmt->execute();
        $order_items = $itemStmt->get_result();
    } else {
        $error = 'Order not found!';
    }
} else {
    $error = 'Invalid order ID!';
}
?>
<link rel="stylesheet" href="../assets/css/orderconfirmationstyle.css">

<main class="order-confirmation">
    <h2>Order Confirmation</h2>
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
        <p>Thank you for your order! Here are the details:</p>

        <table class="order-items">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $order_items->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= (int) $item['quantity']; ?></td>
                        <td><?= number_format((float) $item['price'], 2); ?> PKR</td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <p class="total">Total Price: <?= number_format((float) $order['total_price'], 2); ?> PKR</p>
        <p class="status">Status: <?= htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="index.php" class="btn">Continue Shopping</a>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>