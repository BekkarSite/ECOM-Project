<?php
// Compute base web path so assets work from domain root or subfolder
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projRoot = str_replace('\\', '/', realpath(__DIR__ . '/../../../'));
$baseUri = '';
if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
    $baseUri = rtrim(substr($projRoot, strlen($docRoot)), '/');
}
$BASE_PATH = $baseUri ? '/' . ltrim($baseUri, '/') : '';

// Load settings and DB to support dynamic logo and potential global checks
require_once __DIR__ . '/../../helpers/settings.php';
require_once __DIR__ . '/../../../config/db.php';

// Authentication guard: only protect sensitive pages, allow storefront to be public
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
// Pages that require a logged-in customer
$protected = [
    'dashboard.php',
    'orders.php',
    'order.php',
    'profile.php',
    'checkout.php',
    'reorder.php',
    'cancel_order.php',
];
if (!isset($_SESSION['user_id']) && in_array($currentScript, $protected, true)) {
    $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: login.php' . ($next ? ('?next=' . $next) : ''));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- public_header.php -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/custom/typography.css">
    <link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/custom/headerstyle.css">
    <link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/custom/footerstyle.css">
    <link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/custom/theme.css">
    <?php
    // Dynamic site logo
    $publicLogoPath = get_setting($conn, 'site_logo', 'assets/images/logo.png');
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    function nav_active($current, $target) { return $current === $target ? ' active' : ''; }
    ?>
    <script>
        // Hide the loader once the page has fully loaded
        window.addEventListener('load', function() {
            var loader = document.getElementById('loader');
            if (loader) loader.classList.add('hidden');
        });
    </script>
    </head>
<body>
    <div id="loader">
        <img src="<?= $BASE_PATH ?>/assets/images/loading.gif" alt="Loading...">
    </div>

    <nav class="navbar navbar-expand-lg navbar-light theme-navbar sticky-top">
        <div class="container">
            <!-- Sidebar (offcanvas) toggle shown on mobile -->
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Open menu">
                <i class="fa fa-bars"></i>
            </button>
            <a href="index.php" class="navbar-brand d-flex align-items-center gap-2">
                <img src="<?= $BASE_PATH ?>/<?php echo htmlspecialchars($publicLogoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="d-inline-block align-text-top">
            </a>

            <button class="navbar-toggler d-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link<?php echo nav_active($current, 'index.php'); ?>" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo nav_active($current, 'products.php'); ?>" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo nav_active($current, 'categories.php'); ?>" href="categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo nav_active($current, 'about.php'); ?>" href="about.php">About Us</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo nav_active($current, 'contact.php'); ?>" href="contact.php">Contact</a></li>
                </ul>

                <form action="search.php" method="GET" class="d-flex mb-2 mb-lg-0 w-100 w-lg-auto" role="search">
                    <input class="form-control" type="text" name="query" placeholder="Search products..." required>
                    <button class="btn btn-outline-primary ms-2" type="submit"><i class="fa fa-search"></i></button>
                </form>

                <ul class="navbar-nav ms-lg-3 mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item me-lg-3">
                        <a href="cart.php" class="nav-link position-relative">
                            <i class="fa fa-shopping-cart"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count">
                                <?php echo array_sum($_SESSION['cart'] ?? []); ?>
                            </span>
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="accountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user me-1"></i> Account
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountDropdown">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fa fa-gauge-high me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="orders.php"><i class="fa fa-receipt me-2"></i>Orders</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fa fa-id-badge me-2"></i>Profile & Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="btn btn-outline-secondary me-2" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-primary" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Offcanvas Sidebar Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="sidebarMenuLabel">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column gap-3">
            <!-- Search -->
            <form action="search.php" method="GET" role="search">
                <div class="input-group">
                    <input class="form-control" type="text" name="query" placeholder="Search products..." required>
                    <button class="btn btn-outline-primary" type="submit"><i class="fa fa-search"></i></button>
                </div>
            </form>

            <!-- Primary Nav Links -->
            <div>
                <div class="list-group">
                    <a class="list-group-item list-group-item-action<?php echo nav_active($current, 'index.php'); ?>" href="index.php"><i class="fa fa-house me-2"></i>Home</a>
                    <a class="list-group-item list-group-item-action<?php echo nav_active($current, 'products.php'); ?>" href="products.php"><i class="fa fa-box-open me-2"></i>Products</a>
                    <a class="list-group-item list-group-item-action<?php echo nav_active($current, 'categories.php'); ?>" href="categories.php"><i class="fa fa-tags me-2"></i>Categories</a>
                    <a class="list-group-item list-group-item-action<?php echo nav_active($current, 'about.php'); ?>" href="about.php"><i class="fa fa-circle-info me-2"></i>About Us</a>
                    <a class="list-group-item list-group-item-action<?php echo nav_active($current, 'contact.php'); ?>" href="contact.php"><i class="fa fa-envelope me-2"></i>Contact</a>
                </div>
            </div>

            <!-- Account / Auth -->
            <div>
                <h6 class="text-uppercase text-muted mb-2">Account</h6>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="list-group">
                        <a class="list-group-item list-group-item-action" href="dashboard.php"><i class="fa fa-gauge-high me-2"></i>Dashboard</a>
                        <a class="list-group-item list-group-item-action" href="orders.php"><i class="fa fa-receipt me-2"></i>Orders</a>
                        <a class="list-group-item list-group-item-action" href="profile.php"><i class="fa fa-id-badge me-2"></i>Profile & Settings</a>
                        <a class="list-group-item list-group-item-action" href="logout.php"><i class="fa fa-right-from-bracket me-2"></i>Logout</a>
                    </div>
                <?php else: ?>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary w-50" href="login.php">Login</a>
                        <a class="btn btn-primary w-50" href="register.php">Register</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cart quick access -->
            <div class="mt-auto">
                <a href="cart.php" class="btn btn-outline-dark w-100 position-relative">
                    <i class="fa fa-shopping-cart me-2"></i> View Cart
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo array_sum($_SESSION['cart'] ?? []); ?>
                    </span>
                </a>
            </div>
        </div>
    </div>
