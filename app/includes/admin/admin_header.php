<?php
// Admin Header (Redesigned)
// Includes unified admin styles and renders the top navigation bar

require_once __DIR__ . '/../../helpers/settings.php';
require_once __DIR__ . '/../../../config/db.php';
$logoPath = get_setting($conn, 'site_logo', 'assets/images/logo.png');

$adminName  = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$adminEmail = htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8');

// Compute base web path so assets work from domain root or subfolder
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$projRoot = str_replace('\\', '/', realpath(__DIR__ . '/../../../'));
$baseUri = '';
if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
    $baseUri = rtrim(substr($projRoot, strlen($docRoot)), '/');
}
$BASE_PATH = $baseUri ? '/' . ltrim($baseUri, '/') : '';
?>
<link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-1pA7QzlQv/CU7nzhbW4QEi2qk2ZVjGv8gJYkTn3LQ2mK0m1V6x9r4YkU/1H2Og6g5c5u9uUpOqctZC4YgXy4Vg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= $BASE_PATH ?>/assets/css/custom/admin.css">

<div id="loader">
    <img src="<?= $BASE_PATH ?>/assets/images/loading.gif" alt="Loading...">
</div>

<nav class="navbar navbar-expand-lg navbar-dark admin-navbar">
  <div class="container-fluid px-3 px-lg-4">
    <div class="d-flex align-items-center w-100">
      <!-- Sidebar toggle (mobile) + Brand -->
      <div class="d-flex align-items-center flex-shrink-0">
        <button id="sidebarToggle" class="btn nav-icon-btn me-2 d-lg-none" type="button" aria-label="Toggle sidebar">
          <i class="fa fa-bars"></i>
        </button>
        <a href="dashboard.php" class="navbar-brand d-flex align-items-center">
          <img src="<?= $BASE_PATH ?>/<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="d-inline-block align-text-top">
        </a>
      </div>

      <!-- Navbar collapse toggler (mobile) -->
      <button class="navbar-toggler ms-auto d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="adminNavbar">
        <!-- Primary nav links -->
        <ul class="navbar-nav me-3 mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fa fa-gauge-high me-1"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="manage_orders.php"><i class="fa fa-receipt me-1"></i> Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="manage_products.php"><i class="fa fa-box me-1"></i> Products</a></li>
          <li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="fa fa-users me-1"></i> Users</a></li>
        </ul>

        <!-- Search -->
        <form class="d-flex flex-grow-1 me-lg-3 my-2 my-lg-0" role="search" action="manage_products.php" method="get">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-0 text-secondary"><i class="fa fa-magnifying-glass"></i></span>
            <input class="form-control" type="search" placeholder="Search products, orders, customers..." aria-label="Search" name="search" />
          </div>
        </form>

        <!-- Right actions -->
        <div class="d-flex align-items-center ms-auto gap-2">
          <!-- Quick add dropdown -->
          <div class="dropdown">
            <button class="btn nav-icon-btn d-flex align-items-center gap-2" type="button" id="quickAddMenu" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-plus"></i>
              <span class="d-none d-xl-inline">New</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="quickAddMenu">
              <li><a class="dropdown-item" href="add_product.php"><i class="fa fa-box me-2"></i>Product</a></li>
              <li><a class="dropdown-item" href="manage_categories.php"><i class="fa fa-layer-group me-2"></i>Category</a></li>
              <li><a class="dropdown-item" href="manage_users.php"><i class="fa fa-user-plus me-2"></i>User</a></li>
            </ul>
          </div>

          <!-- Notifications dropdown -->
          <div class="dropdown">
            <button class="btn nav-icon-btn position-relative" type="button" id="notifMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
              <i class="fa fa-bell"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px;">3</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifMenu" style="min-width: 280px;">
              <li class="dropdown-header">Recent activity</li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item small" href="manage_orders.php"><i class="fa fa-receipt me-2 text-secondary"></i>2 new orders placed</a></li>
              <li><a class="dropdown-item small" href="inventory.php"><i class="fa fa-triangle-exclamation me-2 text-warning"></i>Low stock alerts</a></li>
              <li><a class="dropdown-item small" href="support.php"><i class="fa fa-life-ring me-2 text-info"></i>Support ticket updated</a></li>
            </ul>
          </div>

          <!-- Theme toggle (stub) -->
          <button class="btn nav-icon-btn" type="button" id="themeToggle" aria-label="Toggle theme">
            <i class="fa fa-sun"></i>
          </button>

          <!-- Profile dropdown -->
          <div class="dropdown">
            <button class="btn nav-icon-btn d-flex align-items-center justify-content-center" type="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Profile">
              <i class="fa fa-user-shield"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
              <li class="dropdown-header small">
                <div class="fw-semibold"><?php echo $adminName; ?></div>
                <div class="text-muted"><?php echo $adminEmail ?: 'Administrator'; ?></div>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="general_settings.php"><i class="fa fa-gear me-2"></i>Settings</a></li>
              <li><a class="dropdown-item" href="manage_users.php"><i class="fa fa-users me-2"></i>Users</a></li>
              <li><a class="dropdown-item" href="manage_orders.php"><i class="fa fa-receipt me-2"></i>Orders</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="../public/logout.php"><i class="fa fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
  // Keep the CSS variable --nav-height in sync with the actual header height
  (function() {
    function syncNavHeight() {
      var nav = document.querySelector('.admin-navbar');
      if (nav && document.documentElement) {
        document.documentElement.style.setProperty('--nav-height', nav.offsetHeight + 'px');
      }
    }
    document.addEventListener('DOMContentLoaded', syncNavHeight);
    window.addEventListener('load', syncNavHeight);
    window.addEventListener('resize', syncNavHeight);
  })();
  // Simple sidebar toggle for mobile
  (function() {
    var btn = document.getElementById('sidebarToggle');
    if (btn) {
      btn.addEventListener('click', function() {
        document.body.classList.toggle('sidebar-open');
      });
    }
  })();
</script>
