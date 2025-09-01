<!-- Shared header for admin pages -->
<?php
$adminName  = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$adminPhone = htmlspecialchars($_SESSION['admin_phone'] ?? '', ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<header class="admin-header">
    <div class="admin-logo">
        <a href="dashboard.php">Admin Panel</a>
    </div>
    <div class="admin-notifications">
        <a href="#" aria-label="Notifications">Notifications</a>
    </div>
    <div class="admin-user-profile">
        <span class="admin-name"><?php echo $adminName; ?></span>
        <?php if (!empty($adminPhone)): ?>
            <span class="admin-phone"><?php echo $adminPhone; ?></span>
        <?php endif; ?>
        <?php if (!empty($adminEmail)): ?>
            <span class="admin-email"><?php echo $adminEmail; ?></span>
        <?php endif; ?>
        <a href="../public/logout.php" class="admin-logout">Logout</a>
    </div>
</header>