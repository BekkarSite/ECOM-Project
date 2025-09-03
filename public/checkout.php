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
    echo '<main class="checkout container py-5"><div class="alert alert-info">Your cart is empty.</div></main>';
    require_once __DIR__ . '/../app/includes/public/public_footer.php';
    exit();
}

// Build a snapshot for display (non-locking)
$total = 0.0;
$items = [];
foreach ($cart as $product_id => $quantity) {
    $stmt = $conn->prepare('SELECT id, name, price, stock FROM products WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $name = $row['name'];
            $price = (float) $row['price'];
            $stock = (int) $row['stock'];
            $qty = max(1, (int)$quantity);
            $subtotal = $price * $qty;
            $total += $subtotal;
            $items[] = [
                'id' => (int)$row['id'],
                'name' => $name,
                'quantity' => $qty,
                'price' => $price,
                'stock' => $stock,
                'subtotal' => $subtotal,
            ];
        }
        $stmt->close();
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $user_id = (int) $_SESSION['user_id'];

    if ($address === '' || $payment_method === '') {
        $error = 'Please provide shipping address and payment method.';
    } else {
        try {
            // Ensure inventory_movements table exists (audit trail)
            $conn->query("CREATE TABLE IF NOT EXISTS inventory_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                change_qty INT NOT NULL,
                reason VARCHAR(50) NOT NULL,
                order_id INT DEFAULT NULL,
                admin_id INT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX(product_id), INDEX(order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $conn->begin_transaction();

            // Lock rows and validate stock
            $locked = [];
            foreach ($cart as $pid => $qty) {
                $pid = (int)$pid; $qty = max(1, (int)$qty);
                $stmt = $conn->prepare('SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE');
                if (!$stmt) { throw new Exception('DB error.'); }
                $stmt->bind_param('i', $pid);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if (!$row) { throw new Exception('Product not found (ID: ' . $pid . ').'); }
                if ((int)$row['stock'] < $qty) {
                    throw new Exception('Insufficient stock for ' . $row['name'] . '. Available: ' . (int)$row['stock'] . ', requested: ' . $qty . '.');
                }
                $locked[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'price' => (float)$row['price'],
                    'qty' => $qty,
                ];
            }

            // Compute order total fresh under lock
            $order_total = 0.0;
            foreach ($locked as $it) { $order_total += $it['price'] * $it['qty']; }

            // Insert order
            $status = 'pending';
            $stmt = $conn->prepare('INSERT INTO orders (user_id, total_price, status) VALUES (?, ?, ?)');
            if (!$stmt) { throw new Exception('DB error creating order.'); }
            $stmt->bind_param('ids', $user_id, $order_total, $status);
            if (!$stmt->execute()) { throw new Exception('Failed to place order.'); }
            $order_id = $stmt->insert_id;
            $stmt->close();

            // Insert order details and decrement stock
            $detailStmt = $conn->prepare('INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            if (!$detailStmt) { throw new Exception('DB error creating order details.'); }
            $updStmt = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            if (!$updStmt) { throw new Exception('DB error updating stock.'); }
            $logStmt = $conn->prepare('INSERT INTO inventory_movements (product_id, change_qty, reason, order_id, admin_id) VALUES (?, ?, ?, ?, NULL)');
            if (!$logStmt) { throw new Exception('DB error logging inventory.'); }
            foreach ($locked as $it) {
                $detailStmt->bind_param('iiid', $order_id, $it['id'], $it['qty'], $it['price']);
                if (!$detailStmt->execute()) { throw new Exception('Failed to save order items.'); }
                $updStmt->bind_param('ii', $it['qty'], $it['id']);
                if (!$updStmt->execute()) { throw new Exception('Failed to update stock.'); }
                $reason = 'sale';
                $negQty = -1 * (int)$it['qty'];
                $logStmt->bind_param('iisi', $it['id'], $negQty, $reason, $order_id);
                $logStmt->execute();
            }
            $detailStmt->close();
            $updStmt->close();
            $logStmt->close();

            $conn->commit();

            unset($_SESSION['cart']);
            header('Location: order_confirmation.php?order_id=' . $order_id);
            exit();
        } catch (Exception $e) {
            if ($conn->errno === 0) { // transaction may be active
                $conn->rollback();
            }
            $error = $e->getMessage();
        }
    }
}
?>

<link rel="stylesheet" href="../assets/css/custom/checkoutstyle.css">

<main class="checkout container py-4">
    <h1 class="mb-4">Checkout</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Your Cart</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($it['name']); ?></td>
                                        <td class="text-center"><?php echo (int)$it['quantity']; ?></td>
                                        <td class="text-end"><?php echo htmlspecialchars(number_format($it['price'], 2)); ?> PKR</td>
                                        <td class="text-end fw-semibold"><?php echo htmlspecialchars(number_format($it['subtotal'], 2)); ?> PKR</td>
                                        <td class="text-end"><?php echo (int)$it['stock']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Total</th>
                                    <th class="text-end h6 mb-0"><?php echo htmlspecialchars(number_format($total, 2)); ?> PKR</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <form method="POST" class="card">
                <div class="card-header bg-white"><strong>Shipping & Payment</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="address" class="form-label">Shipping Address</label>
                        <textarea class="form-control" name="address" id="address" rows="4" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="" disabled <?php echo empty($_POST['payment_method']) ? 'selected' : ''; ?>>Select...</option>
                            <option value="credit_card" <?php echo (($_POST['payment_method'] ?? '') === 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="paypal" <?php echo (($_POST['payment_method'] ?? '') === 'paypal') ? 'selected' : ''; ?>>PayPal</option>
                            <option value="cod" <?php echo (($_POST['payment_method'] ?? '') === 'cod') ? 'selected' : ''; ?>>Cash on Delivery</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Place Order</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
