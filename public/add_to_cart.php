<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_GET['product_id'], $_GET['quantity'])) {
    if ($isAjax) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Missing parameters']);
    } else {
        header('Location: products.php');
    }
    exit();
}

$product_id = (int) $_GET['product_id'];
$quantity = max(1, (int) $_GET['quantity']);

$stmt = $conn->prepare('SELECT id FROM products WHERE id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();
$exists = $result && $result->fetch_assoc();
$stmt->close();

if (!$exists) {
    if ($isAjax) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid product']);
    } else {
        header('Location: products.php');
    }
    exit();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + $quantity;

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['count' => array_sum($_SESSION['cart'])]);
} else {
    header('Location: cart.php');
}
exit();
