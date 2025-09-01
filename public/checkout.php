<!-- public/checkout.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$cart = $_SESSION['cart'] ?? [];
if (count($cart) === 0) {
    echo '<main class="checkout"><p>Your cart is empty.</p></main>';
    require_once __DIR__ . '/../app/includes/public/public_footer.php';
    exit();
}

$total = 0;
$items = [];
foreach ($cart as $product_id => $quantity) {
    $stmt = $conn->prepare('SELECT name, price FROM products WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $name = $row['name'];
            $price = (float) $row['price'];
            $subtotal = $price * $quantity;
            $total += $subtotal;
            $items[] = [
                'id' => $product_id,
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal,
            ];
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = $_POST['address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $user_id = (int) $_SESSION['user_id'];
    $status = 'pending';

    $stmt = $conn->prepare('INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('ids', $user_id, $total, $status);
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;
            $stmt->close();
            $detailStmt = $conn->prepare(
                'INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)'
            );
            if ($detailStmt) {
                foreach ($items as $item) {
                    $detailStmt->bind_param('iiid', $order_id, $item['id'], $item['quantity'], $item['price']);
                    $detailStmt->execute();
                }
                $detailStmt->close();
            }

            unset($_SESSION['cart']);
            header('Location: order_confirmation.php?order_id=' . $order_id);
            exit();
        }

        $stmt->close();
    }
    echo '<p>Error placing order.</p>';
}
?>

<link rel="stylesheet" href="../assets/css/custom/checkoutstyle.css">

<main class="checkout">
    <h1>Checkout</h1>

    <h2>Your Cart</h2>
    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?> -
                Quantity: <?= (int) $item['quantity'] ?> -
                Price: <?= number_format($item['price'], 2) ?> PKR
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Total Price: <?= number_format($total, 2) ?> PKR</h3>

    <h2>Shipping Information</h2>
    <form method="POST">
        <label for="address">Shipping Address:</label>
        <textarea name="address" id="address" required></textarea>

        <label for="payment_method">Payment Method:</label>
        <select name="payment_method" id="payment_method" required>
            <option value="credit_card">Credit Card</option>
            <option value="paypal">PayPal</option>
        </select>

        <button type="submit">Place Order</button>
    </form>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>