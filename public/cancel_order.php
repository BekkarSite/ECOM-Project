<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit();
}

$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    $_SESSION['flash_error'] = 'Invalid CSRF token.';
    header('Location: orders.php');
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($order_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid order.';
    header('Location: orders.php');
    exit();
}

// Check order ownership and status
$stmt = $conn->prepare('SELECT id, status FROM orders WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['flash_error'] = 'Order not found.';
    header('Location: orders.php');
    exit();
}
if ($order['status'] !== 'pending') {
    $_SESSION['flash_error'] = 'Only pending orders can be cancelled.';
    header('Location: order.php?id=' . $order_id);
    exit();
}

try {
    // Ensure inventory_movements exists
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

    // Update order status first
    $upd = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $upd->bind_param('i', $order_id);
    if (!$upd->execute()) { throw new Exception('Failed to cancel order.'); }
    $upd->close();

    // Fetch items
    $items = [];
    $st = $conn->prepare('SELECT product_id, quantity FROM order_details WHERE order_id = ?');
    $st->bind_param('i', $order_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) { $items[] = $row; }
    $st->close();

    // Restock and log movements
    $restock = $conn->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $log = $conn->prepare('INSERT INTO inventory_movements (product_id, change_qty, reason, order_id, admin_id) VALUES (?, ?, ?, ?, NULL)');
    foreach ($items as $it) {
        $pid = (int)$it['product_id'];
        $qty = max(1, (int)$it['quantity']);
        $restock->bind_param('ii', $qty, $pid);
        if (!$restock->execute()) { throw new Exception('Failed to restock product #' . $pid); }
        $reason = 'order_cancelled';
        $log->bind_param('iisi', $pid, $qty, $reason, $order_id);
        $log->execute();
    }
    $restock->close();
    $log->close();

    $conn->commit();
    $_SESSION['flash_success'] = 'Order cancelled and items restocked.';
} catch (Exception $e) {
    if ($conn->errno === 0) { $conn->rollback(); }
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: order.php?id=' . $order_id);
exit();
