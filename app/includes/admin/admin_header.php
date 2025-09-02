<?php
// Admin Header (Redesigned)
// Includes unified admin styles and renders the top navigation bar

require_once __DIR__ . '/../../helpers/settings.php';
require_once __DIR__ . '/../../../config/db.php';
$logoPath = get_setting($conn, 'site_logo', 'assets/images/logo.png');

$adminName  = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-1pA7QzlQv/CU7nzhbW4QEi2qk2ZVjGv8gJYkTn3LQ2mK0m1V6x9r4YkU/1H2Og6g5c5u9uUpOqctZC4YgXy4Vg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="../assets/css/custom/admin.css">

<div id="loader">
    <img src="../assets/images/loading.gif" alt="Loading...">
</div>

<nav class="navbar navbar-expand-lg admin-navbar">
  <div class="container">
    <div class="d-flex align-items-center">
      <button id="sidebarToggle" class="btn nav-icon-btn me-2 d-lg-none" type="button" aria-label="Toggle sidebar">
        <i class="fa fa-bars"></i>
      </button>
      <a href="dashboard.php" class="navbar-brand d-flex align-items-center">
        <img src="../<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="d-inline-block align-text-top">
      </a>
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="adminNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="manage_orders.php">Orders</a></li>
        <li class="nav-item"><a class="nav-link" href="manage_products.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="manage_users.php">Users</a></li>
      </ul>

      <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center gap-2">
        <li class="nav-item d-none d-lg-block">
          <span class="nav-link disabled" aria-disabled="true">
            <i class="fa fa-user-shield me-1"></i>
            <?php echo $adminName; ?><?php echo $adminEmail ? ' · ' . $adminEmail : ''; ?>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="../public/logout.php" title="Logout">
            <i class="fa fa-arrow-right-from-bracket"></i>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
