<!-- public/cart.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

$cart = $_SESSION['cart'] ?? [];

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;

    switch ($action) {
        case 'increase':
            if ($productId !== null) {
                $cart[$productId] = ($cart[$productId] ?? 0) + 1;
            }
            break;
        case 'decrease':
            if ($productId !== null && isset($cart[$productId])) {
                $cart[$productId]--;
                if ($cart[$productId] <= 0) {
                    unset($cart[$productId]);
                }
            }
            break;
        case 'empty':
            $cart = [];
            break;
    }

    $_SESSION['cart'] = $cart;

    // Handle AJAX requests for cart updates
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');

        $response = ['count' => array_sum($cart)];

        if ($productId !== null) {
            $quantity = $cart[$productId] ?? 0;
            $price = 0;
            if ($quantity > 0) {
                $stmt = $conn->prepare('SELECT price FROM products WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $productId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $price = (float) $row['price'];
                    }
                    $stmt->close();
                }
            }
            $response['product_id'] = $productId;
            $response['quantity'] = $quantity;
            $response['subtotal'] = $quantity * $price;
            $response['removed'] = $quantity === 0;
        }

        $total = 0;
        foreach ($cart as $pid => $qty) {
            $stmt = $conn->prepare('SELECT price FROM products WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $pid);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $total += (float) $row['price'] * $qty;
                }
                $stmt->close();
            }
        }
        $response['total'] = $total;

        echo json_encode($response);
        exit;
    }
}
?>

<link rel="stylesheet" href="../assets/css/custom/cartstyle.css">

<main class="cart">
    <h1>Your Cart</h1>
    <?php if (count($cart) > 0): ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total = 0;
                foreach ($cart as $product_id => $quantity):
                    $stmt = $conn->prepare('SELECT name, price FROM products WHERE id = ?');
                    if ($stmt) {
                        $stmt->bind_param('i', $product_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()):
                            $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                            $price = (float) $row['price'];
                            $subtotal = $price * $quantity;
                            $total += $subtotal;
                ?>
                            <tr>
                                <td><?= $name ?></td>
                                <td class="quantity-controls" data-product-id="<?= (int) $product_id ?>">
                                    <a href="cart.php?action=decrease&amp;product_id=<?= (int) $product_id ?>" class="qty-btn">-</a>
                                    <span class="qty"><?= (int) $quantity ?></span>
                                    <a href="cart.php?action=increase&amp;product_id=<?= (int) $product_id ?>" class="qty-btn">+</a>
                                </td>
                                <td><?= number_format($price, 2) ?> PKR</td>
                                <td class="subtotal"><?= number_format($subtotal, 2) ?> PKR</td>
                                </tr>
                <?php
                        endif;
                        $stmt->close();
                    }
                endforeach;
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong>Total</strong></td>
                    <td><strong id="cart-total"><?= number_format($total, 2) ?> PKR</strong></td>
                    </tr>
            </tfoot>
        </table>
        <p>
            <a href="cart.php?action=empty" class="empty-button">Empty Cart</a>
            <a href="checkout.php" class="checkout-button">Proceed to Checkout</a>
        </p>
    <?php else: ?>
        <p>Your cart is empty.</p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>