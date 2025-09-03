<?php
// admin/delete_product.php (POST only, CSRF protected)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: manage_products.php'); exit(); }

$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $csrf)) {
    $_SESSION['flash_error'] = 'Invalid CSRF token.';
    header('Location: manage_products.php');
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$redirect = $_POST['redirect'] ?? 'manage_products.php';

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid product ID.';
    header('Location: ' . $redirect);
    exit();
}

if ($stmt = $conn->prepare('DELETE FROM products WHERE id = ?')) {
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = 'Product deleted successfully.';
    } else {
        $_SESSION['flash_error'] = 'Failed to delete product.';
    }
    $stmt->close();
} else {
    $_SESSION['flash_error'] = 'Failed to prepare delete statement.';
}

header('Location: ' . $redirect);
exit();
