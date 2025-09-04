<?php
session_start();
require_once __DIR__ . '/../config/db.php';
// Compute BASE_PATH for redirects (works under subfolders)
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$baseUri = '';
if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
    $baseUri = rtrim(substr($projRoot, strlen($docRoot)), '/');
}
$BASE_PATH = $baseUri ? '/' . ltrim($baseUri, '/') : '';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_GET['product_id'], $_GET['quantity'])) {
    if ($isAjax) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Missing parameters']);
    } else {
        header('Location: ' . $BASE_PATH . '/products.php');
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
        header('Location: ' . $BASE_PATH . '/products.php');
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
    header('Location: ' . $BASE_PATH . '/cart.php');
}
exit();
