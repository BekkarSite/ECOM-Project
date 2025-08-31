<!-- Shared header for admin pages -->
<?php
$adminEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<header class="admin-header">
    <div class="admin-logo">
        <a href="dashboard.php">Admin Panel</a>
    </div>
    <nav class="admin-nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="manage_orders.php">Orders</a></li>
            <li><a href="manage_products.php">Products</a></li>
            <li><a href="manage_users.php">Users</a></li>
            <li><a href="manage_categories.php">Categories</a></li>
            <li><a href="../public/logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="admin-user">Logged in as <?php echo $adminEmail; ?></div>
</header>