<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$status  = '';

// Ensure payment_gateways table exists
$conn->query("CREATE TABLE IF NOT EXISTS payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    config TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default gateways if not present
$seed = [
    [
        'code' => 'cod',
        'name' => 'Cash on Delivery',
        'enabled' => 1,
        'config' => json_encode(['instructions' => 'Pay with cash upon delivery'])
    ],
    [
        'code' => 'paypal',
        'name' => 'PayPal',
        'enabled' => 0,
        'config' => json_encode(['mode' => 'sandbox', 'client_id' => '', 'client_secret' => '', 'currency' => 'PKR'])
    ],
    [
        'code' => 'stripe',
        'name' => 'Stripe',
        'enabled' => 0,
        'config' => json_encode(['publishable_key' => '', 'secret_key' => '', 'currency' => 'PKR'])
    ],
    [
        'code' => 'jazzcash',
        'name' => 'JazzCash',
        'enabled' => 0,
        'config' => json_encode([
            'mode' => 'sandbox',
            'merchant_id' => '',
            'password' => '',
            'integrity_salt' => '',
            'return_url' => '',
            'currency' => 'PKR'
        ])
    ],
    [
        'code' => 'easypaisa',
        'name' => 'Easypaisa',
        'enabled' => 0,
        'config' => json_encode([
            'mode' => 'sandbox',
            'store_id' => '',
            'api_key' => '',
            'hash_key' => '',
            'return_url' => '',
            'currency' => 'PKR'
        ])
    ],
];
foreach ($seed as $g) {
    $stmt = $conn->prepare('SELECT id FROM payment_gateways WHERE code = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $g['code']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $ins = $conn->prepare('INSERT INTO payment_gateways (code, name, enabled, config) VALUES (?, ?, ?, ?)');
            if ($ins) {
                $ins->bind_param('ssis', $g['code'], $g['name'], $g['enabled'], $g['config']);
                $ins->execute();
                $ins->close();
            }
        }
        $stmt->close();
    }
}

function loadGateways(mysqli $conn): array {
    $list = [];
    $res = $conn->query('SELECT code, name, enabled, config FROM payment_gateways ORDER BY name ASC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cfg = [];
            if (!empty($row['config'])) {
                $decoded = json_decode($row['config'], true);
                if (is_array($decoded)) { $cfg = $decoded; }
            }
            $list[$row['code']] = [
                'name' => $row['name'],
                'enabled' => (int)$row['enabled'] === 1,
                'config' => $cfg,
            ];
        }
        $res->close();
    }
    return $list;
}

$gateways = loadGateways($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $message = 'Invalid security token.';
        $status  = 'danger';
    } else {
        $allowed = ['cod', 'paypal', 'stripe', 'jazzcash', 'easypaisa'];
        $updates = $_POST['gateway'] ?? [];
        foreach ($allowed as $code) {
            $data = $updates[$code] ?? [];
            $enabled = isset($data['enabled']) && $data['enabled'] === '1' ? 1 : 0;

            // Build sanitized config per gateway
            $config = [];
            if ($code === 'cod') {
                $config['instructions'] = trim($data['instructions'] ?? '');
            } elseif ($code === 'paypal') {
                $mode = strtolower(trim($data['mode'] ?? 'sandbox'));
                if (!in_array($mode, ['sandbox', 'live'], true)) { $mode = 'sandbox'; }
                $config['mode'] = $mode;
                $config['client_id'] = trim($data['client_id'] ?? '');
                $config['client_secret'] = trim($data['client_secret'] ?? '');
                $currency = strtoupper(trim($data['currency'] ?? 'PKR'));
                if ($currency === '') { $currency = 'PKR'; }
                $config['currency'] = $currency;
            } elseif ($code === 'stripe') {
                $config['publishable_key'] = trim($data['publishable_key'] ?? '');
                $config['secret_key'] = trim($data['secret_key'] ?? '');
                $currency = strtoupper(trim($data['currency'] ?? 'PKR'));
                if ($currency === '') { $currency = 'PKR'; }
                $config['currency'] = $currency;
            } elseif ($code === 'jazzcash') {
                $mode = strtolower(trim($data['mode'] ?? 'sandbox'));
                if (!in_array($mode, ['sandbox', 'live'], true)) { $mode = 'sandbox'; }
                $config['mode'] = $mode;
                $config['merchant_id'] = trim($data['merchant_id'] ?? '');
                $config['password'] = trim($data['password'] ?? '');
                $config['integrity_salt'] = trim($data['integrity_salt'] ?? '');
                $config['return_url'] = trim($data['return_url'] ?? '');
                $currency = strtoupper(trim($data['currency'] ?? 'PKR'));
                if ($currency === '') { $currency = 'PKR'; }
                $config['currency'] = $currency;
            } elseif ($code === 'easypaisa') {
                $mode = strtolower(trim($data['mode'] ?? 'sandbox'));
                if (!in_array($mode, ['sandbox', 'live'], true)) { $mode = 'sandbox'; }
                $config['mode'] = $mode;
                $config['store_id'] = trim($data['store_id'] ?? '');
                $config['api_key'] = trim($data['api_key'] ?? '');
                $config['hash_key'] = trim($data['hash_key'] ?? '');
                $config['return_url'] = trim($data['return_url'] ?? '');
                $currency = strtoupper(trim($data['currency'] ?? 'PKR'));
                if ($currency === '') { $currency = 'PKR'; }
                $config['currency'] = $currency;
            }

            $json = json_encode($config, JSON_UNESCAPED_SLASHES);
            $stmt = $conn->prepare('UPDATE payment_gateways SET enabled = ?, config = ? WHERE code = ?');
            if ($stmt) {
                $stmt->bind_param('iss', $enabled, $json, $code);
                $stmt->execute();
                $stmt->close();
            }
        }
        $gateways = loadGateways($conn);
        $message = 'Payment methods updated.';
        $status  = 'success';
    }
}

function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Methods</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="page-header">
            <div>
                <h1 class="h3 mb-1">Payment Methods</h1>
                <div class="text-muted-600">Enable and configure payment gateways for checkout</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>" role="alert">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">

            <div class="row g-3">
                <!-- COD -->
                <?php $cod = $gateways['cod'] ?? ['name' => 'Cash on Delivery', 'enabled' => false, 'config' => []]; ?>
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">Cash on Delivery</h5>
                                <span class="badge-soft">Offline</span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="cod_enabled" name="gateway[cod][enabled]" value="1" <?php echo $cod['enabled'] ? 'checked' : ''; ?> />
                                <label class="form-check-label" for="cod_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="cod_instructions" class="form-label fw-semibold">Instructions</label>
                                <textarea class="form-control" id="cod_instructions" name="gateway[cod][instructions]" rows="2" placeholder="Any instructions shown to customers choosing COD"><?php echo e($cod['config']['instructions'] ?? ''); ?></textarea>
                            </div>
                            <div class="text-muted small">Customers will pay in cash upon delivery. No online processing.</div>
                        </div>
                    </div>
                </div>

                <!-- PayPal -->
                <?php $pp = $gateways['paypal'] ?? ['name' => 'PayPal', 'enabled' => false, 'config' => []]; ?>
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">PayPal</h5>
                                <span class="badge-soft"><i class="bi bi-globe me-1"></i>Online</span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="paypal_enabled" name="gateway[paypal][enabled]" value="1" <?php echo $pp['enabled'] ? 'checked' : ''; ?> />
                                <label class="form-check-label" for="paypal_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="paypal_mode">Mode</label>
                                    <select class="form-select" id="paypal_mode" name="gateway[paypal][mode]">
                                        <?php $mode = strtolower($pp['config']['mode'] ?? 'sandbox'); ?>
                                        <option value="sandbox" <?php echo $mode === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                                        <option value="live" <?php echo $mode === 'live' ? 'selected' : ''; ?>>Live</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="paypal_currency">Currency</label>
                                    <input type="text" class="form-control" id="paypal_currency" name="gateway[paypal][currency]" value="<?php echo e($pp['config']['currency'] ?? 'PKR'); ?>" placeholder="e.g., PKR">
                                </div>
                                <div class="col-12"></div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="paypal_client_id">Client ID</label>
                                    <input type="text" class="form-control" id="paypal_client_id" name="gateway[paypal][client_id]" value="<?php echo e($pp['config']['client_id'] ?? ''); ?>" placeholder="PayPal Client ID">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="paypal_client_secret">Client Secret</label>
                                    <input type="text" class="form-control" id="paypal_client_secret" name="gateway[paypal][client_secret]" value="<?php echo e($pp['config']['client_secret'] ?? ''); ?>" placeholder="PayPal Client Secret">
                                </div>
                            </div>
                            <div class="text-muted small mt-2">Create credentials in your PayPal Developer dashboard. Use Sandbox for testing.</div>
                        </div>
                    </div>
                </div>

                <!-- Stripe -->
                <?php $st = $gateways['stripe'] ?? ['name' => 'Stripe', 'enabled' => false, 'config' => []]; ?>
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">Stripe</h5>
                                <span class="badge-soft"><i class="bi bi-globe me-1"></i>Online</span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="stripe_enabled" name="gateway[stripe][enabled]" value="1" <?php echo $st['enabled'] ? 'checked' : ''; ?> />
                                <label class="form-check-label" for="stripe_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="stripe_currency">Currency</label>
                                    <input type="text" class="form-control" id="stripe_currency" name="gateway[stripe][currency]" value="<?php echo e($st['config']['currency'] ?? 'PKR'); ?>" placeholder="e.g., PKR">
                                </div>
                                <div class="col-12"></div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="stripe_publishable_key">Publishable Key</label>
                                    <input type="text" class="form-control" id="stripe_publishable_key" name="gateway[stripe][publishable_key]" value="<?php echo e($st['config']['publishable_key'] ?? ''); ?>" placeholder="pk_live_... or pk_test_...">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="stripe_secret_key">Secret Key</label>
                                    <input type="text" class="form-control" id="stripe_secret_key" name="gateway[stripe][secret_key]" value="<?php echo e($st['config']['secret_key'] ?? ''); ?>" placeholder="sk_live_... or sk_test_...">
                                </div>
                            </div>
                            <div class="text-muted small mt-2">Get keys from your Stripe dashboard. Use test keys for development.</div>
                        </div>
                    </div>
                </div>

                <!-- JazzCash -->
                <?php $jc = $gateways['jazzcash'] ?? ['name' => 'JazzCash', 'enabled' => false, 'config' => []]; ?>
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">JazzCash</h5>
                                <span class="badge-soft"><i class="bi bi-globe me-1"></i>Online</span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="jazzcash_enabled" name="gateway[jazzcash][enabled]" value="1" <?php echo $jc['enabled'] ? 'checked' : ''; ?> />
                                <label class="form-check-label" for="jazzcash_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="jazzcash_mode">Mode</label>
                                    <?php $jc_mode = strtolower($jc['config']['mode'] ?? 'sandbox'); ?>
                                    <select class="form-select" id="jazzcash_mode" name="gateway[jazzcash][mode]">
                                        <option value="sandbox" <?php echo $jc_mode === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                                        <option value="live" <?php echo $jc_mode === 'live' ? 'selected' : ''; ?>>Live</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="jazzcash_currency">Currency</label>
                                    <input type="text" class="form-control" id="jazzcash_currency" name="gateway[jazzcash][currency]" value="<?php echo e($jc['config']['currency'] ?? 'PKR'); ?>" placeholder="PKR">
                                </div>
                                <div class="col-12"></div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="jazzcash_merchant_id">Merchant ID</label>
                                    <input type="text" class="form-control" id="jazzcash_merchant_id" name="gateway[jazzcash][merchant_id]" value="<?php echo e($jc['config']['merchant_id'] ?? ''); ?>" placeholder="Merchant ID">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="jazzcash_password">Password</label>
                                    <input type="text" class="form-control" id="jazzcash_password" name="gateway[jazzcash][password]" value="<?php echo e($jc['config']['password'] ?? ''); ?>" placeholder="Password">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="jazzcash_integrity_salt">Integrity Salt</label>
                                    <input type="text" class="form-control" id="jazzcash_integrity_salt" name="gateway[jazzcash][integrity_salt]" value="<?php echo e($jc['config']['integrity_salt'] ?? ''); ?>" placeholder="Integrity Salt">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="jazzcash_return_url">Return URL</label>
                                    <input type="text" class="form-control" id="jazzcash_return_url" name="gateway[jazzcash][return_url]" value="<?php echo e($jc['config']['return_url'] ?? ''); ?>" placeholder="https://yourdomain.com/payments/jazzcash_callback.php">
                                </div>
                            </div>
                            <div class="text-muted small mt-2">Use JazzCash merchant portal credentials. Set Return URL to your payment callback endpoint.</div>
                        </div>
                    </div>
                </div>

                <!-- Easypaisa -->
                <?php $ep = $gateways['easypaisa'] ?? ['name' => 'Easypaisa', 'enabled' => false, 'config' => []]; ?>
                <div class="col-12">
                    <div class="card shadow-soft">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="mb-0">Easypaisa</h5>
                                <span class="badge-soft"><i class="bi bi-globe me-1"></i>Online</span>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="easypaisa_enabled" name="gateway[easypaisa][enabled]" value="1" <?php echo $ep['enabled'] ? 'checked' : ''; ?> />
                                <label class="form-check-label" for="easypaisa_enabled">Enabled</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="easypaisa_mode">Mode</label>
                                    <?php $ep_mode = strtolower($ep['config']['mode'] ?? 'sandbox'); ?>
                                    <select class="form-select" id="easypaisa_mode" name="gateway[easypaisa][mode]">
                                        <option value="sandbox" <?php echo $ep_mode === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                                        <option value="live" <?php echo $ep_mode === 'live' ? 'selected' : ''; ?>>Live</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="easypaisa_currency">Currency</label>
                                    <input type="text" class="form-control" id="easypaisa_currency" name="gateway[easypaisa][currency]" value="<?php echo e($ep['config']['currency'] ?? 'PKR'); ?>" placeholder="PKR">
                                </div>
                                <div class="col-12"></div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="easypaisa_store_id">Store ID</label>
                                    <input type="text" class="form-control" id="easypaisa_store_id" name="gateway[easypaisa][store_id]" value="<?php echo e($ep['config']['store_id'] ?? ''); ?>" placeholder="Store ID">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="easypaisa_api_key">API Key</label>
                                    <input type="text" class="form-control" id="easypaisa_api_key" name="gateway[easypaisa][api_key]" value="<?php echo e($ep['config']['api_key'] ?? ''); ?>" placeholder="API Key / Password">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold" for="easypaisa_hash_key">Hash Key</label>
                                    <input type="text" class="form-control" id="easypaisa_hash_key" name="gateway[easypaisa][hash_key]" value="<?php echo e($ep['config']['hash_key'] ?? ''); ?>" placeholder="Hash Key">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="easypaisa_return_url">Return URL</label>
                                    <input type="text" class="form-control" id="easypaisa_return_url" name="gateway[easypaisa][return_url]" value="<?php echo e($ep['config']['return_url'] ?? ''); ?>" placeholder="https://yourdomain.com/payments/easypaisa_callback.php">
                                </div>
                            </div>
                            <div class="text-muted small mt-2">Use Easypaisa merchant credentials. Set Return URL to your callback endpoint.</div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-admin-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
            </div>
        </form>

        <div class="mt-4 text-muted small">
            <strong>Note:</strong> This config page stores gateway credentials in the database (payment_gateways.config). Ensure your database access is secure and backups are protected.
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
