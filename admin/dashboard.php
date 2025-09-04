<?php
session_start();

// Ensure only authenticated admins can access the dashboard
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Include the database connection to fetch summary statistics
require_once __DIR__ . '/../config/db.php';

// Helpers
function getCount(mysqli $conn, string $table): int {
    $sql = "SELECT COUNT(*) AS count FROM {$table}";
    $res = $conn->query($sql);
    if ($res) { $row = $res->fetch_assoc(); return (int)($row['count'] ?? 0); }
    return 0;
}
function getSum(mysqli $conn, string $table, string $column, string $where = ''): float {
    $whereSql = $where ? " WHERE $where" : '';
    $sql = "SELECT COALESCE(SUM($column), 0) AS total FROM {$table}{$whereSql}";
    $res = $conn->query($sql);
    if ($res) { $row = $res->fetch_assoc(); return (float)($row['total'] ?? 0.0); }
    return 0.0;
}
function getCountWhere(mysqli $conn, string $table, string $where): int {
    $sql = "SELECT COUNT(*) AS count FROM {$table} WHERE {$where}";
    $res = $conn->query($sql);
    if ($res) { $row = $res->fetch_assoc(); return (int)($row['count'] ?? 0); }
    return 0;
}

// Core Metrics
$productCount   = getCount($conn, 'products');
$orderCount     = getCount($conn, 'orders');
$customerCount  = getCountWhere($conn, 'users', "role <> 'admin'"); // treat non-admins as customers
$pendingOrders  = getCountWhere($conn, 'orders', "status = 'pending'");
$paidOrders     = getCountWhere($conn, 'orders', "status = 'paid'");
$totalRevenue   = getSum($conn, 'orders', 'total_price');

// Recent Orders (id desc as a proxy for most recent)
$recentOrders = $conn->query("SELECT id, user_id, total_price, status FROM orders ORDER BY id DESC LIMIT 8");

// Low stock products
$lowStock = $conn->query("SELECT id, name, stock FROM products WHERE stock <= 5 ORDER BY stock ASC, id ASC LIMIT 8");

// Top categories by product count (if categories table exists)
$topCategories = null;
$topCategories = $conn->query("SELECT c.name, COUNT(p.id) as cnt
  FROM categories c LEFT JOIN products p ON p.category_id = c.id
  GROUP BY c.id, c.name ORDER BY cnt DESC, c.name ASC LIMIT 5");

// System status (from bootstrap-admin tables if present)
$currenciesCount = 0; $enabledGateways = 0; $allGateways = 0;
if ($res = $conn->query("SHOW TABLES LIKE 'currencies'")) {
    if ($res->num_rows > 0) {
        $cRes = $conn->query("SELECT COUNT(*) AS c FROM currencies");
        if ($cRes) { $r = $cRes->fetch_assoc(); $currenciesCount = (int)($r['c'] ?? 0); }
    }
}
if ($res = $conn->query("SHOW TABLES LIKE 'payment_gateways'")) {
    if ($res->num_rows > 0) {
        $pgRes1 = $conn->query("SELECT COUNT(*) AS c FROM payment_gateways");
        if ($pgRes1) { $r = $pgRes1->fetch_assoc(); $allGateways = (int)($r['c'] ?? 0); }
        $pgRes2 = $conn->query("SELECT SUM(enabled) AS e FROM payment_gateways");
        if ($pgRes2) { $r2 = $pgRes2->fetch_assoc(); $enabledGateways = (int)($r2['e'] ?? 0); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
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
                <h1 class="h3 mb-1">Dashboard</h1>
                <div class="text-muted-600">Overview of your store and POS operations</div>
            </div>
            <div class="d-flex gap-2">
                <a href="add_product.php" class="btn btn-admin-primary"><i class="bi bi-plus-lg me-1"></i> Add Product</a>
                <a href="manage_orders.php" class="btn btn-outline-secondary"><i class="bi bi-receipt me-1"></i> Manage Orders</a>
                <a href="pos.php" class="btn btn-outline-secondary"><i class="bi bi-cash-coin me-1"></i> POS Register</a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted-600">Orders</div>
                                <div class="h4 mb-0"><?php echo number_format($orderCount); ?></div>
                            </div>
                            <div class="badge-soft"><i class="bi bi-receipt"></i></div>
                        </div>
                        <div class="small text-muted mt-2">Pending: <?php echo number_format($pendingOrders); ?> · Paid: <?php echo number_format($paidOrders); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted-600">Revenue</div>
                                <div class="h4 mb-0"><?php echo number_format($totalRevenue, 2); ?></div>
                            </div>
                            <div class="badge-soft"><i class="bi bi-currency-dollar"></i></div>
                        </div>
                        <div class="small text-muted mt-2">All-time gross</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted-600">Products</div>
                                <div class="h4 mb-0"><?php echo number_format($productCount); ?></div>
                            </div>
                            <div class="badge-soft"><i class="bi bi-box"></i></div>
                        </div>
                        <div class="small text-muted mt-2">Catalog size</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card shadow-soft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted-600">Customers</div>
                                <div class="h4 mb-0"><?php echo number_format($customerCount); ?></div>
                            </div>
                            <div class="badge-soft"><i class="bi bi-people"></i></div>
                        </div>
                        <div class="small text-muted mt-2">Registered customers</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card shadow-soft h-100">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Orders</h5>
                            <a href="manage_orders.php" class="btn btn-sm btn-outline-secondary">View all</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>
                                    <?php while ($o = $recentOrders->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo (int)$o['id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$o['user_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo number_format((float)$o['total_price'], 2); ?></td>
                                            <td><span class="badge-soft"><?php echo htmlspecialchars((string)$o['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>
                                                <a href="manage_orders.php" class="text-decoration-none">Manage</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted">No recent orders found.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card shadow-soft mb-3">
                    <div class="card-header bg-white"><h5 class="mb-0">Low Stock</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($lowStock && $lowStock->num_rows > 0): ?>
                                    <?php while ($p = $lowStock->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?php echo (int)$p['stock']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No low stock items.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card shadow-soft">
                    <div class="card-header bg-white"><h5 class="mb-0">Top Categories</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table admin-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-end">Products</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if ($topCategories && $topCategories->num_rows > 0): ?>
                                    <?php while ($c = $topCategories->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?php echo (int)$c['cnt']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" class="text-center text-muted">No category data.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-8">
                <div class="card shadow-soft h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Operations & POS</h5>
                        <div class="d-flex gap-2">
                            <a href="manage_products.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box me-1"></i> Products</a>
                            <a href="manage_orders.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt me-1"></i> Orders</a>
                            <a href="manage_users.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-people me-1"></i> Customers</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="p-3 rounded-12 border">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="badge-soft me-2"><i class="bi bi-cash-coin"></i></div>
                                        <strong>POS Register</strong>
                                    </div>
                                    <p class="mb-2 text-muted">Launch a simple in-store checkout to create walk-in sales.</p>
                                    <a href="pos.php" class="btn btn-admin-primary btn-sm">Open POS</a>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="p-3 rounded-12 border">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="badge-soft me-2"><i class="bi bi-upc-scan"></i></div>
                                        <strong>Quick Scan</strong>
                                    </div>
                                    <p class="mb-2 text-muted">Use barcode scanner in the POS to search and add products.</p>
                                    <a href="manage_products.php" class="btn btn-outline-secondary btn-sm">Go to Products</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-soft h-100">
                    <div class="card-header bg-white"><h5 class="mb-0">System Status</h5></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>Currencies configured</span>
                            <strong><?php echo (int)$currenciesCount; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>Payment gateways</span>
                            <strong><?php echo (int)$enabledGateways; ?> / <?php echo (int)$allGateways; ?> enabled</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span>Catalog size</span>
                            <strong><?php echo number_format($productCount); ?> products</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
