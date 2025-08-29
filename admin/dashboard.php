<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
</head>
<body>
    <h1>Admin Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['email'], ENT_QUOTES, 'UTF-8'); ?>!</p>
    <nav>
        <ul>
            <li><a href="manage_products.php">Manage Products</a></li>
            <li><a href="../public/logout.php">Logout</a></li>
        </ul>
    </nav>
</body>
</html>