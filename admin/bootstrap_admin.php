<?php
// admin/bootstrap_admin.php
// One-time bootstrap installer to create/upgrade core tables for advanced admin features
// Protect this file. Remove it after successful run.
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';

$results = [];

function runQuery(mysqli $conn, string $sql, string $label)
{
    global $results;
    $ok = $conn->query($sql);
    if ($ok) {
        $results[] = [true, $label];
    } else {
        $results[] = [false, $label . ' — ERROR: ' . $conn->error];
    }
}

function tableExists(mysqli $conn, string $table): bool
{
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    return $res && $res->num_rows > 0;
}

// Core tables
runQuery($conn, "CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(191) NOT NULL,
  value TEXT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Ensure settings table');

runQuery($conn, "CREATE TABLE IF NOT EXISTS permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create permissions');

runQuery($conn, "CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  INDEX (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create role_permissions');

// Roles table may already exist. Create if missing.
if (!tableExists($conn, 'roles')) {
    runQuery($conn, "CREATE TABLE roles (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(191) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create roles');
}

// Users table assumed exists; otherwise create minimal users (admin area depends on it)
if (!tableExists($conn, 'users')) {
    runQuery($conn, "CREATE TABLE users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(191) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      role VARCHAR(191) NOT NULL DEFAULT 'Customer',
      is_banned TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create users (minimal)');
}

// Currencies
runQuery($conn, "CREATE TABLE IF NOT EXISTS currencies (
  code CHAR(3) PRIMARY KEY,
  name VARCHAR(64) NOT NULL,
  symbol VARCHAR(8) NOT NULL,
  rate DECIMAL(16,8) NOT NULL DEFAULT 1.0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create currencies');

// Payment Gateways
runQuery($conn, "CREATE TABLE IF NOT EXISTS payment_gateways (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  config TEXT NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create payment_gateways');

// Taxes
runQuery($conn, "CREATE TABLE IF NOT EXISTS tax_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  country VARCHAR(2) NULL,
  region VARCHAR(64) NULL,
  product_type VARCHAR(64) NULL,
  rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  is_compound TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create tax_rules');

// Coupons / Discounts codes
runQuery($conn, "CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  description TEXT NULL,
  discount_type ENUM('percent','fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  min_order DECIMAL(10,2) NULL,
  usage_limit INT NULL,
  per_user_limit INT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create coupons');

runQuery($conn, "CREATE TABLE IF NOT EXISTS coupon_uses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  user_id INT NULL,
  order_id INT NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (coupon_id), INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create coupon_uses');

// Automated price/discount rules
runQuery($conn, "CREATE TABLE IF NOT EXISTS price_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  conditions_json TEXT NOT NULL,
  actions_json TEXT NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create price_rules (automated discounts)');

// Shipping
runQuery($conn, "CREATE TABLE IF NOT EXISTS shipping_zones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create shipping_zones');

runQuery($conn, "CREATE TABLE IF NOT EXISTS shipping_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  carrier VARCHAR(64) NULL,
  type ENUM('flat','weight','carrier','free') NOT NULL DEFAULT 'flat',
  config TEXT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create shipping_methods');

runQuery($conn, "CREATE TABLE IF NOT EXISTS shipping_zone_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zone_id INT NOT NULL,
  method_id INT NOT NULL,
  country_code VARCHAR(2) NULL,
  region VARCHAR(64) NULL,
  postcode_pattern VARCHAR(64) NULL,
  min_weight DECIMAL(10,3) NULL,
  max_weight DECIMAL(10,3) NULL,
  rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  INDEX(zone_id), INDEX(method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create shipping_zone_rates');

// Support & Help Center
runQuery($conn, "CREATE TABLE IF NOT EXISTS support_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  subject VARCHAR(191) NOT NULL,
  status ENUM('open','in_progress','awaiting_customer','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create support_tickets');

runQuery($conn, "CREATE TABLE IF NOT EXISTS ticket_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  author_type ENUM('user','admin') NOT NULL,
  author_id INT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create ticket_messages');

runQuery($conn, "CREATE TABLE IF NOT EXISTS help_articles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(191) NOT NULL UNIQUE,
  title VARCHAR(191) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create help_articles');

// Affiliates & Vendors
runQuery($conn, "CREATE TABLE IF NOT EXISTS affiliates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  code VARCHAR(64) NOT NULL UNIQUE,
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create affiliates');

runQuery($conn, "CREATE TABLE IF NOT EXISTS affiliate_referrals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  affiliate_id INT NOT NULL,
  referred_user_id INT NULL,
  order_id INT NULL,
  commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','approved','paid','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(affiliate_id), INDEX(referred_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create affiliate_referrals');

runQuery($conn, "CREATE TABLE IF NOT EXISTS vendors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  name VARCHAR(191) NOT NULL,
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create vendors');

runQuery($conn, "CREATE TABLE IF NOT EXISTS vendor_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendor_id INT NOT NULL,
  product_id INT NOT NULL,
  UNIQUE KEY (vendor_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create vendor_products');

// Notifications & Auditing
runQuery($conn, "CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(64) NOT NULL,
  payload JSON NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create notifications');

runQuery($conn, "CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NULL,
  action VARCHAR(191) NOT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create audit_logs');

// Security: 2FA
runQuery($conn, "CREATE TABLE IF NOT EXISTS two_factor_secrets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  secret VARCHAR(128) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create two_factor_secrets');

// Abandoned carts
runQuery($conn, "CREATE TABLE IF NOT EXISTS abandoned_carts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  session_id VARCHAR(191) NULL,
  payload MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create abandoned_carts');

// Subscriptions & recurring billing (basic)
runQuery($conn, "CREATE TABLE IF NOT EXISTS subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  status ENUM('active','past_due','canceled','paused') NOT NULL DEFAULT 'active',
  gateway VARCHAR(64) NULL,
  gateway_sub_id VARCHAR(191) NULL,
  interval_unit ENUM('day','week','month','year') NOT NULL DEFAULT 'month',
  interval_count INT NOT NULL DEFAULT 1,
  current_period_end DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create subscriptions');

runQuery($conn, "CREATE TABLE IF NOT EXISTS subscription_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subscription_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  INDEX(subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create subscription_items');

runQuery($conn, "CREATE TABLE IF NOT EXISTS subscription_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subscription_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PKR',
  status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  INDEX(subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create subscription_invoices');

// API & Integrations
runQuery($conn, "CREATE TABLE IF NOT EXISTS api_keys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  token VARCHAR(191) NOT NULL UNIQUE,
  permissions TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create api_keys');

runQuery($conn, "CREATE TABLE IF NOT EXISTS integration_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(64) NOT NULL,
  config TEXT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create integration_settings');

// Analytics snapshots (basic)
runQuery($conn, "CREATE TABLE IF NOT EXISTS analytics_snapshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  metric VARCHAR(64) NOT NULL,
  value DECIMAL(18,4) NOT NULL,
  captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(metric), INDEX(captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;", 'Create analytics_snapshots');

// Seed defaults
// Default currencies
$conn->query("INSERT IGNORE INTO currencies (code, name, symbol, rate, is_default) VALUES
('PKR','Pakistani Rupee','Rs',1.0,1),
('USD','US Dollar','$',285.00000000,0),
('EUR','Euro','€',310.00000000,0)");

// Payment gateways placeholders
$conn->query("INSERT IGNORE INTO payment_gateways (name, enabled, config) VALUES
('PayPal', 0, '{"client_id":"","secret":"","mode":"sandbox"}'),
('Stripe', 0, '{"publishable_key":"","secret_key":""}')");

// Create Admin role if missing and map all permissions
$adminRoleId = null;
if ($stmt = $conn->prepare('SELECT id FROM roles WHERE name = ?')) {
    $roleName = 'Admin';
    $stmt->bind_param('s', $roleName);
    $stmt->execute();
    $stmt->bind_result($adminRoleId);
    if (!$stmt->fetch()) {
        $stmt->close();
        $conn->query("INSERT INTO roles (name) VALUES ('Admin')");
        $adminRoleId = (int)$conn->insert_id;
    } else {
        $stmt->close();
    }
}

// Seed permissions
$perms = [
    'settings.manage',
    'users.view','users.manage','roles.manage',
    'currencies.manage','payments.manage','taxes.manage',
    'products.view','products.manage','inventory.manage',
    'bundles.manage','offers.manage',
    'orders.view','orders.manage','shipping.manage','shipping.zones.manage',
    'discounts.manage','coupons.manage','price_rules.manage',
    'support.tickets.manage','help_center.manage',
    'affiliates.manage','vendors.manage',
    'marketing.campaigns.manage','ads.manage','social.share',
    'analytics.view','reports.view','abandoned_carts.view',
    'security.manage','backups.manage','audit.view',
    'dashboard.customize','notifications.view',
    'ai.recommendations.manage','dynamic_pricing.manage',
    'loyalty.manage','referrals.manage',
    'checkout.customize',
    'suppliers.manage','purchase_orders.manage',
    'multistore.manage','multivendor.manage',
    'reviews.manage','reviews.respond',
    'subscriptions.manage','compliance.taxes.manage',
    'content.pages.manage','seo.manage',
    'api.manage','integrations.manage',
    'localization.manage'
];
foreach ($perms as $p) {
    $pEsc = $conn->real_escape_string($p);
    $conn->query("INSERT IGNORE INTO permissions (name) VALUES ('{$pEsc}')");
}

// Grant all permissions to Admin role
if ($adminRoleId) {
    $res = $conn->query('SELECT id FROM permissions');
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['id'];
        $conn->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES ({$adminRoleId}, {$pid})");
    }
}

// Ensure some general settings keys exist
$defaultSettings = [
    'site_title' => 'My eCommerce',
    'site_tagline' => 'Shop smarter.',
    'site_description' => 'Modern eCommerce platform.',
    'company_name' => 'My Company',
    'company_email' => 'support@example.com',
    'company_phone' => '+1 234 567 890',
    'company_address' => '123 Main Street, City, Country',
    'social_facebook' => '',
    'social_twitter' => '',
    'social_instagram' => '',
    'site_logo' => 'assets/images/logo.png'
];
foreach ($defaultSettings as $k => $v) {
    $kEsc = $conn->real_escape_string($k);
    $vEsc = $conn->real_escape_string($v);
    $conn->query("INSERT IGNORE INTO settings (name, value) VALUES ('{$kEsc}', '{$vEsc}')");
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Bootstrap Installer</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <style>
    body { padding: 24px; }
    .ok { color: #0a7a0a; }
    .err { color: #a10a0a; }
    .box { border: 1px solid #ddd; padding: 16px; border-radius: 8px; }
  </style>
</head>
<body>
  <h1>Admin Bootstrap Installer</h1>
  <p>This script created or verified core tables required for the advanced admin features. You can safely delete this file after a successful run.</p>
  <div class="box">
    <h3>Results</h3>
    <ul>
      <?php foreach ($results as $r): [$ok, $label] = $r; ?>
        <li class="<?php echo $ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="box" style="margin-top:16px;">
    <h3>Next Steps</h3>
    <ol>
      <li>Update Admin sidebar to link to new modules (Currencies, Payment Gateways, Taxes, Coupons, Shipping Zones/Methods, Support, Help Center, Affiliates, Vendors, Analytics, Marketing, Integrations, API Keys, Subscriptions).</li>
      <li>Build CRUD pages for each module using these tables.</li>
      <li>Harden security: CSRF tokens, permission checks per-page, enable 2FA.</li>
      <li>Remove admin/bootstrap_admin.php after completion.</li>
    </ol>
  </div>
</body>
</html>