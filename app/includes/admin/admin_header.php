<!-- Admin header using public navbar styling -->
<!-- Minimal CSS includes to ensure header renders consistently across admin pages -->
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="../assets/css/custom/headerstyle.css">
<link rel="stylesheet" href="../assets/css/custom/footerstyle.css">
<?php
// Load dynamic logo from settings if available
require_once __DIR__ . '/../../helpers/settings.php';
require_once __DIR__ . '/../../../config/db.php';
$logoPath = get_setting($conn, 'site_logo', 'assets/images/logo.png');
?>
<?php
$adminName  = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
?>

<div id="loader">
    <img src="../assets/images/loading.gif" alt="Loading...">
    </div>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a href="dashboard.php" class="navbar-brand">
            <img src="../<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="d-inline-block align-text-top">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_products.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_orders.php">Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_users.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="manage_categories.php">Categories</a></li>
            </ul>
            <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item me-lg-3">
                    <span class="nav-link disabled" aria-disabled="true">
                        <i class="fa fa-user-shield"></i>
                        <?php echo $adminName; ?><?php echo $adminEmail ? ' · ' . $adminEmail : ''; ?>
                    </span>
                </li>
                <li class="nav-item"><a class="nav-link" href="../public/logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
