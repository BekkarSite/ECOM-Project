<!-- public/cart.php -->
<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

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
    }
?>

<link rel="stylesheet" href="../assets/css/cartstyle.css">

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
                                <td class="quantity-controls">
                                    <a href="cart.php?action=decrease&amp;product_id=<?= (int) $product_id ?>" class="qty-btn">-</a>
                                    <?= (int) $quantity ?>
                                    <a href="cart.php?action=increase&amp;product_id=<?= (int) $product_id ?>" class="qty-btn">+</a>
                                </td>
                                <td><?= number_format($price, 2) ?> PKR</td>
                                <td><?= number_format($subtotal, 2) ?> PKR</td>
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
                    <td><strong><?= number_format($total, 2) ?> PKR</strong></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>