<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/helpers/settings.php';

$message = '';
$errors = [];

// Ensure coupons table exists for Discounts & Promotions
$conn->query("CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    discount_type ENUM('percent','fixed') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expires_at DATETIME NULL,
    usage_limit INT NULL,
    usage_count INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    conditions TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function sanitize_bool($v): string { return $v ? '1' : '0'; }
function settings_get_json(mysqli $conn, string $key, $default = []) {
    $raw = get_setting($conn, $key, null);
    if ($raw === null || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}
function settings_set_json(mysqli $conn, string $key, $value): bool {
    return set_setting($conn, $key, json_encode($value, JSON_UNESCAPED_SLASHES));
}

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'save_currency':
            $default = trim($_POST['currency_default'] ?? 'USD');
            $supportedRaw = trim($_POST['currency_supported'] ?? '');
            $supported = array_values(array_filter(array_map('trim', explode(',', strtoupper($supportedRaw)))));
            if (empty($supported)) { $supported = [$default]; }
            set_setting($conn, 'currency_default', strtoupper($default));
            settings_set_json($conn, 'currency_supported', $supported);
            $message = 'Currency settings saved.';
            break;
        case 'fetch_rates':
            $base = get_setting($conn, 'currency_default', 'USD');
            $apiUrl = 'https://api.exchangerate.host/latest?base=' . urlencode($base);
            $resp = @file_get_contents($apiUrl);
            if ($resp !== false) {
                $data = json_decode($resp, true);
                if (isset($data['rates'])) {
                    $payload = [
                        'base' => $data['base'] ?? $base,
                        'date' => $data['date'] ?? date('Y-m-d'),
                        'rates' => $data['rates'],
                        'fetched_at' => date('c')
                    ];
                    settings_set_json($conn, 'currency_rates', $payload);
                    $message = 'Exchange rates updated.';
                } else {
                    $errors[] = 'Unexpected response from exchange API.';
                }
            } else {
                $errors[] = 'Failed to contact exchange API.';
            }
            break;
        case 'save_payment_gateways':
            $paypal_enabled = isset($_POST['paypal_enabled']);
            $paypal_client  = trim($_POST['paypal_client_id'] ?? '');
            $paypal_secret  = trim($_POST['paypal_secret'] ?? '');
            $stripe_enabled = isset($_POST['stripe_enabled']);
            $stripe_pk      = trim($_POST['stripe_publishable_key'] ?? '');
            $stripe_sk      = trim($_POST['stripe_secret_key'] ?? '');
            $cod_enabled    = isset($_POST['cod_enabled']);
            $gateways = [
                'paypal' => [
                    'enabled' => (bool)$paypal_enabled,
                    'client_id' => $paypal_client,
                    'secret' => $paypal_secret,
                ],
                'stripe' => [
                    'enabled' => (bool)$stripe_enabled,
                    'publishable_key' => $stripe_pk,
                    'secret_key' => $stripe_sk,
                ],
                'cod' => [
                    'enabled' => (bool)$cod_enabled,
                ],
            ];
            settings_set_json($conn, 'payment_gateways', $gateways);
            $message = 'Payment gateway settings saved.';
            break;
        case 'save_tax':
            $default_rate = (float)($_POST['tax_default_rate'] ?? 0);
            $rules_json   = trim($_POST['tax_rules_json'] ?? '[]');
            $rules = json_decode($rules_json, true);
            if ($rules_json !== '' && $rules === null && json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON for tax rules.';
            } else {
                $tax = [
                    'default_rate' => $default_rate,
                    'rules' => is_array($rules) ? $rules : []
                ];
                settings_set_json($conn, 'tax_settings', $tax);
                $message = 'Tax settings saved.';
            }
            break;
        case 'save_shipping':
            $zones_json   = trim($_POST['shipping_zones_json'] ?? '[]');
            $methods_json = trim($_POST['shipping_methods_json'] ?? '[]');
            $zones = json_decode($zones_json, true);
            $methods = json_decode($methods_json, true);
            if (($zones_json !== '' && $zones === null && json_last_error() !== JSON_ERROR_NONE) ||
                ($methods_json !== '' && $methods === null && json_last_error() !== JSON_ERROR_NONE)) {
                $errors[] = 'Invalid JSON for shipping zones or methods.';
            } else {
                $shipping = [
                    'zones' => is_array($zones) ? $zones : [],
                    'methods' => is_array($methods) ? $methods : []
                ];
                settings_set_json($conn, 'shipping_settings', $shipping);
                $message = 'Shipping settings saved.';
            }
            break;
        case 'save_security':
            $twofa = isset($_POST['two_factor_enabled']);
            $backups_schedule = trim($_POST['backups_schedule'] ?? '');
            $firewall = isset($_POST['firewall_enabled']);
            $log_days = (int)($_POST['activity_log_retention_days'] ?? 30);
            $security = [
                'two_factor_enabled' => (bool)$twofa,
                'backups_schedule' => $backups_schedule,
                'firewall_enabled' => (bool)$firewall,
                'activity_log_retention_days' => $log_days,
            ];
            settings_set_json($conn, 'security_settings', $security);
            $message = 'Security settings saved.';
            break;
        case 'add_coupon':
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount_type = $_POST['discount_type'] === 'percent' ? 'percent' : 'fixed';
            $amount = (float)($_POST['amount'] ?? 0);
            $expires_at = trim($_POST['expires_at'] ?? '');
            $usage_limit = strlen($_POST['usage_limit'] ?? '') ? (int)$_POST['usage_limit'] : null;
            $active = isset($_POST['active']) ? 1 : 0;
            $conditions = trim($_POST['conditions_json'] ?? '');

            if ($code === '' || $amount <= 0) {
                $errors[] = 'Coupon code and valid amount are required.';
            } else {
                $stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, amount, expires_at, usage_limit, active, conditions) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $expires = $expires_at !== '' ? $expires_at : null;
                    $stmt->bind_param('ssdsiis', $code, $discount_type, $amount, $expires, $usage_limit, $active, $conditions);
                    if ($stmt->execute()) {
                        $message = 'Coupon added.';
                    } else {
                        $errors[] = 'Failed to add coupon (duplicate code?)';
                    }
                    $stmt->close();
                }
            }
            break;
    }
}

// Handle coupon deletion via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'delete_coupon') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM coupons WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Coupon deleted.';
        }
    }
}

// Load current settings
$currency_default   = get_setting($conn, 'currency_default', 'USD');
$currency_supported = settings_get_json($conn, 'currency_supported', [$currency_default]);
$currency_rates     = settings_get_json($conn, 'currency_rates', []);
$payment_gateways   = settings_get_json($conn, 'payment_gateways', []);
$tax_settings       = settings_get_json($conn, 'tax_settings', ['default_rate' => 0, 'rules' => []]);
$shipping_settings  = settings_get_json($conn, 'shipping_settings', ['zones' => [], 'methods' => []]);
$security_settings  = settings_get_json($conn, 'security_settings', ['two_factor_enabled' => false, 'backups_schedule' => '', 'firewall_enabled' => false, 'activity_log_retention_days' => 30]);

// Load coupons
$coupons = [];
$res = $conn->query('SELECT * FROM coupons ORDER BY id DESC');
if ($res) {
    while ($row = $res->fetch_assoc()) { $coupons[] = $row; }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced Settings</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .tabs a { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333; }
        .tabs a.active { background: #0d6efd; color: #fff; border-color: #0d6efd; }
        .section { display: none; }
        .section.active { display: block; }
        .form-grid { display: grid; grid-template-columns: 220px 1fr; gap: 8px 16px; align-items: center; max-width: 900px; }
        .form-grid label { font-weight: 600; }
        textarea.code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        table.table { max-width: 1000px; }
    </style>
    <script>
        function showTab(id) {
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.tabs a').forEach(a => a.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            var ta = document.querySelector('.tabs a[href="#' + id + '"]');
            if (ta) ta.classList.add('active');
            if (history.pushState) history.pushState(null, '', '#'+id);
        }
        window.addEventListener('DOMContentLoaded', function() {
            var hash = location.hash ? location.hash.substring(1) : 'currency';
            if (!document.getElementById(hash)) hash = 'currency';
            showTab(hash);
        });
    </script>
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<main class="content">
    <h1>Advanced Settings</h1>
    <?php if ($message || $errors): ?>
        <div class="alert <?php echo $errors ? 'alert-danger' : 'alert-success'; ?>" role="alert">
            <?php echo htmlspecialchars(($errors ? implode(' ', $errors) : $message), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <nav class="tabs">
        <a href="#currency" onclick="showTab('currency'); return false;">Currency</a>
        <a href="#payments" onclick="showTab('payments'); return false;">Payments</a>
        <a href="#tax" onclick="showTab('tax'); return false;">Tax & Compliance</a>
        <a href="#shipping" onclick="showTab('shipping'); return false;">Shipping</a>
        <a href="#discounts" onclick="showTab('discounts'); return false;">Discounts & Coupons</a>
        <a href="#security" onclick="showTab('security'); return false;">Security & Backups</a>
    </nav>

    <section id="currency" class="section">
        <h2>Currency Settings</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_currency">
            <label for="currency_default">Default Currency</label>
            <input type="text" id="currency_default" name="currency_default" value="<?php echo htmlspecialchars($currency_default, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., USD" required>
            <label for="currency_supported">Supported Currencies (comma-separated)</label>
            <input type="text" id="currency_supported" name="currency_supported" value="<?php echo htmlspecialchars(implode(',', $currency_supported), ENT_QUOTES, 'UTF-8'); ?>" placeholder="USD,EUR,GBP">
            <div></div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
        <form method="POST" style="margin-top:12px;">
            <input type="hidden" name="action" value="fetch_rates">
            <button type="submit" class="btn btn-outline-secondary">Fetch Latest Rates</button>
            <?php if (!empty($currency_rates)): ?>
                <span style="margin-left:8px;">Last updated: <?php echo htmlspecialchars($currency_rates['fetched_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (Base: <?php echo htmlspecialchars($currency_rates['base'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</span>
            <?php endif; ?>
        </form>
    </section>

    <section id="payments" class="section">
        <h2>Payment Gateways</h2>
        <?php $pg = $payment_gateways; ?>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_payment_gateways">
            <h3 style="grid-column:1 / -1; margin-top:12px;">PayPal</h3>
            <label>Enable PayPal</label>
            <input type="checkbox" name="paypal_enabled" <?php echo !empty($pg['paypal']['enabled']) ? 'checked' : ''; ?>>
            <label>Client ID</label>
            <input type="text" name="paypal_client_id" value="<?php echo htmlspecialchars($pg['paypal']['client_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label>Secret</label>
            <input type="text" name="paypal_secret" value="<?php echo htmlspecialchars($pg['paypal']['secret'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

            <h3 style="grid-column:1 / -1; margin-top:12px;">Stripe</h3>
            <label>Enable Stripe</label>
            <input type="checkbox" name="stripe_enabled" <?php echo !empty($pg['stripe']['enabled']) ? 'checked' : ''; ?>>
            <label>Publishable Key</label>
            <input type="text" name="stripe_publishable_key" value="<?php echo htmlspecialchars($pg['stripe']['publishable_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label>Secret Key</label>
            <input type="text" name="stripe_secret_key" value="<?php echo htmlspecialchars($pg['stripe']['secret_key'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

            <h3 style="grid-column:1 / -1; margin-top:12px;">Cash on Delivery</h3>
            <label>Enable COD</label>
            <input type="checkbox" name="cod_enabled" <?php echo !empty($pg['cod']['enabled']) ? 'checked' : ''; ?>>

            <div></div>
            <button type="submit" class="btn btn-primary">Save Gateways</button>
        </form>
    </section>

    <section id="tax" class="section">
        <h2>Tax & Compliance</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_tax">
            <label>Default Tax Rate (%)</label>
            <input type="number" name="tax_default_rate" step="0.01" value="<?php echo htmlspecialchars((string)($tax_settings['default_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>">
            <label>Tax Rules (JSON)</label>
            <textarea class="code" name="tax_rules_json" rows="6" placeholder='[{"country":"US","state":"CA","rate":8.25}]'><?php echo htmlspecialchars(json_encode($tax_settings['rules'] ?? [], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div></div>
            <button type="submit" class="btn btn-primary">Save Tax Settings</button>
        </form>
    </section>

    <section id="shipping" class="section">
        <h2>Shipping Configuration & Zones</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_shipping">
            <label>Shipping Zones (JSON)</label>
            <textarea class="code" name="shipping_zones_json" rows="6" placeholder='[{"name":"US","countries":["US"],"rates":[{"method":"standard","price":5},{"method":"express","price":15}]}]'><?php echo htmlspecialchars(json_encode($shipping_settings['zones'] ?? [], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            <label>Shipping Methods (JSON)</label>
            <textarea class="code" name="shipping_methods_json" rows="6" placeholder='[{"name":"standard","label":"Standard (3-5 days)"},{"name":"express","label":"Express (1-2 days)"}]'><?php echo htmlspecialchars(json_encode($shipping_settings['methods'] ?? [], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div></div>
            <button type="submit" class="btn btn-primary">Save Shipping Settings</button>
        </form>
    </section>

    <section id="discounts" class="section">
        <h2>Discounts & Coupons</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="add_coupon">
            <label>Code</label>
            <input type="text" name="code" required>
            <label>Type</label>
            <select name="discount_type">
                <option value="percent">Percent %</option>
                <option value="fixed">Fixed Amount</option>
            </select>
            <label>Amount</label>
            <input type="number" name="amount" step="0.01" required>
            <label>Expires At</label>
            <input type="datetime-local" name="expires_at">
            <label>Usage Limit</label>
            <input type="number" name="usage_limit" placeholder="Optional">
            <label>Active</label>
            <input type="checkbox" name="active" checked>
            <label>Conditions (JSON)</label>
            <textarea class="code" name="conditions_json" rows="4" placeholder='{"min_order":50,"applicable_categories":[1,2]}'>
            </textarea>
            <div></div>
            <button type="submit" class="btn btn-primary">Add Coupon</button>
        </form>

        <h3 style="margin-top:20px;">Existing Coupons</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Expires</th>
                    <th>Usage</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coupons as $c): ?>
                    <tr>
                        <td><?php echo (int)$c['id']; ?></td>
                        <td><?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($c['discount_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($c['amount'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($c['expires_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$c['usage_count']; ?><?php echo isset($c['usage_limit']) && $c['usage_limit'] !== null ? ' / ' . (int)$c['usage_limit'] : ''; ?></td>
                        <td><?php echo ((int)$c['active']) ? 'Yes' : 'No'; ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-danger" href="settings_advanced.php?action=delete_coupon&id=<?php echo (int)$c['id']; ?>" onclick="return confirm('Delete this coupon?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section id="security" class="section">
        <h2>Security & Backups</h2>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_security">
            <label>Enable Two-Factor (global)</label>
            <input type="checkbox" name="two_factor_enabled" <?php echo !empty($security_settings['two_factor_enabled']) ? 'checked' : ''; ?>>
            <label>Backups Schedule (cron-like)</label>
            <input type="text" name="backups_schedule" placeholder="e.g., daily 02:00" value="<?php echo htmlspecialchars($security_settings['backups_schedule'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label>Enable Firewall</label>
            <input type="checkbox" name="firewall_enabled" <?php echo !empty($security_settings['firewall_enabled']) ? 'checked' : ''; ?>>
            <label>Activity Log Retention (days)</label>
            <input type="number" name="activity_log_retention_days" value="<?php echo htmlspecialchars((string)($security_settings['activity_log_retention_days'] ?? 30), ENT_QUOTES, 'UTF-8'); ?>">
            <div></div>
            <button type="submit" class="btn btn-primary">Save Security Settings</button>
        </form>
        <p style="margin-top:12px;" class="text-muted">Note: Implement 2FA per-user and backup scheduler in subsequent steps. This page stores configuration and toggles.</p>
    </section>

</main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
