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

// Verify order ownership
$stmt = $conn->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$exists) {
    $_SESSION['flash_error'] = 'Order not found.';
    header('Location: orders.php');
    exit();
}

// Fetch items and add to session cart
$items = [];
$stmt = $conn->prepare('SELECT product_id, quantity FROM order_details WHERE order_id = ?');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }
foreach ($items as $it) {
    $pid = (int)$it['product_id'];
    $qty = max(1, (int)$it['quantity']);
    $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
}

$_SESSION['flash_success'] = 'Items added to cart from order #' . $order_id . '.';
header('Location: cart.php');
exit();
