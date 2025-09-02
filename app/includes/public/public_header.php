<!DOCTYPE html>
<html lang="en">
<!-- public_header.php -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/headerstyle.css">
    <link rel="stylesheet" href="../assets/css/custom/footerstyle.css">
    <?php
    // Dynamic site logo
    require_once __DIR__ . '/../../helpers/settings.php';
    require_once __DIR__ . '/../../../config/db.php';
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
    <style>
        /* Minimal header refinements */
        .navbar-brand img { height: 42px; width: auto; }
        .navbar .nav-link.active { font-weight: 600; }
        #loader { position: fixed; inset: 0; display: grid; place-items: center; background: rgba(255,255,255,.9); z-index: 1055; transition: opacity .3s ease, visibility .3s ease; }
        #loader.hidden { opacity: 0; visibility: hidden; }
        #loader img { width: 72px; height: 72px; object-fit: contain; }
    </style>
</head>
<body>
    <div id="loader">
        <img src="../assets/images/loading.gif" alt="Loading...">
    </div>

    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm">
        <div class="container">
            <a href="index.php" class="navbar-brand d-flex align-items-center gap-2">
                <img src="../<?php echo htmlspecialchars($publicLogoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="d-inline-block align-text-top">
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
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
