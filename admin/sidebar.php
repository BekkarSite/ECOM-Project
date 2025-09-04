<?php
// Redesigned Admin Sidebar
$current = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
function active($file, $current) { return $file === $current ? 'active' : ''; }
?>
<aside class="admin-sidebar">
  <div class="menu-title">Main</div>
  <ul class="nav">
    <li class="nav-item"><a class="nav-link <?php echo active('dashboard.php', $current); ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('manage_orders.php', $current); ?>" href="manage_orders.php"><i class="bi bi-receipt"></i> <span>Orders</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('inventory.php', $current); ?>" href="inventory.php"><i class="bi bi-inboxes"></i> <span>Inventory</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('manage_products.php', $current); ?>" href="manage_products.php"><i class="bi bi-box"></i> <span>Products</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('featured_products.php', $current); ?>" href="featured_products.php"><i class="bi bi-star"></i> <span>Featured</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('manage_categories.php', $current); ?>" href="manage_categories.php"><i class="bi bi-tags"></i> <span>Categories</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('manage_users.php', $current); ?>" href="manage_users.php"><i class="bi bi-people"></i> <span>Users</span></a></li>
  </ul>

  <hr />

  <div class="menu-title">Settings</div>
  <ul class="nav">
    <li class="nav-item"><a class="nav-link <?php echo active('general_settings.php', $current); ?>" href="general_settings.php"><i class="bi bi-gear"></i> <span>General</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('payment_methods.php', $current); ?>" href="payment_methods.php"><i class="bi bi-credit-card"></i> <span>Payments</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('live_chat.php', $current); ?>" href="live_chat.php"><i class="bi bi-chat-dots"></i> <span>Live Chat</span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo active('settings_advanced.php', $current); ?>" href="settings_advanced.php"><i class="bi bi-sliders"></i> <span>Advanced</span></a></li>
  </ul>

  <hr />

  <ul class="nav">
    <li class="nav-item"><a class="nav-link" href="../public/logout.php"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a></li>
  </ul>
</aside>
