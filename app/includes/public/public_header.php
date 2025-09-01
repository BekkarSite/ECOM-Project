<!DOCTYPE html>
<html lang="en">
<!-- header.php -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/custom/headerstyle.css">
    <link rel="stylesheet" href="../assets/css/custom/footerstyle.css">
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

    <header class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <img src="../assets/images/logo.png" alt="Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <form action="search.php" method="GET" class="d-flex me-3" role="search">
                    <input class="form-control me-2" type="text" name="query" placeholder="Search products..." required>
                    <button class="btn btn-outline-success" type="submit"><i class="fa fa-search"></i></button>
                </form>
                <div class="cart-icon me-3 position-relative">
                    <a href="cart.php" class="nav-link">
                        <i class="fa fa-shopping-cart"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count"><?php echo array_sum($_SESSION['cart'] ?? []); ?></span>
                    </a>
                </div>
                <div class="user-auth d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                        <a class="nav-link" href="logout.php">Logout</a>
                    <?php else: ?>
                        <a class="nav-link" href="login.php">Login</a>
                        <a class="nav-link" href="register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>