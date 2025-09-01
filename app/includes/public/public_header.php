<!DOCTYPE html>
<html lang="en">
<!-- header.php -->

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/headerstyle.css">
    <link rel="stylesheet" href="../assets/css/footerstyle.css">
<script>
        // Hide the loader once the page has fully loaded
        window.addEventListener('load', function() {
            var loader = document.getElementById('loader');
            if (loader) {
                loader.classList.add('hidden');
            }
        });
    </script>
</head>

<body>
<div id="loader">
    <img src="../assets/images/loading.gif" alt="Loading...">
</div>

<header>
    <div class="logo-container">
        <a href="index.php" class="logo">
            <img src="../assets/images/logo.png" alt="Logo">
        </a>
    </div>

    <nav class="navbar">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="categories.php">Categories</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
    </nav>

    <div class="header-actions">
        <form action="search.php" method="GET" class="search-form">
            <input type="text" name="query" placeholder="Search products..." required>
            <button type="submit"><i class="fa fa-search"></i></button>
        </form>

        <div class="cart-icon">
            <a href="cart.php">
                <i class="fa fa-shopping-cart"></i>
                <span class="cart-count"><?php echo array_sum($_SESSION['cart'] ?? []); ?></span>
            </a>
        </div>

        <div class="user-auth">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </div>
</header>