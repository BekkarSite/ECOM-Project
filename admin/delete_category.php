<?php
// public/admin/delete_category.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once '../config/db.php';

if (isset($_GET['id'])) {
    $category_id = (int) $_GET['id'];
    $stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->bind_param('i', $category_id);
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: manage_categories.php');
        exit();
    }
    echo 'Error deleting category: ' . $conn->error;
}
?>