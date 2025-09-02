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

// Check order
$stmt = $conn->prepare('SELECT id, status FROM orders WHERE id = ? AND user_id = ?');
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['flash_error'] = 'Order not found.';
} elseif ($order['status'] !== 'pending') {
    $_SESSION['flash_error'] = 'Only pending orders can be cancelled.';
} else {
    $upd = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $upd->bind_param('i', $order_id);
    if ($upd->execute()) {
        $_SESSION['flash_success'] = 'Order cancelled successfully.';
    } else {
        $_SESSION['flash_error'] = 'Failed to cancel order.';
    }
    $upd->close();
}

header('Location: order.php?id=' . $order_id);
exit();
