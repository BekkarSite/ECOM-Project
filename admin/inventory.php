<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// Ensure inventory_movements table exists (audit trail)
$conn->query("CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    change_qty INT NOT NULL,
    reason VARCHAR(50) NOT NULL,
    order_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(product_id), INDEX(order_id), INDEX(admin_id), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// Flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
function flash_set($ok, $msg) { $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $msg; }

// Fetch categories for filters
$categories = [];
if ($res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC")) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
}

// Handle stock adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        flash_set(false, 'Invalid CSRF token.');
        header('Location: inventory.php');
        exit();
    }

    $product_id = (int)($_POST['product_id'] ?? 0);
    $delta = (int)($_POST['delta'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'adjustment');
    $admin_id = (int)$_SESSION['admin_id'];

    if ($product_id <= 0 || $delta === 0) {
        flash_set(false, 'Provide a valid product and non-zero adjustment.');
        header('Location: inventory.php');
        exit();
    }

    try {
        $conn->begin_transaction();

        // Lock product row
        $stmt = $conn->prepare('SELECT id, stock FROM products WHERE id = ? FOR UPDATE');
        if (!$stmt) throw new Exception('DB error.');
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new Exception('Product not found.');

        $newStock = (int)$row['stock'] + $delta;
        if ($newStock < 0) throw new Exception('Resulting stock cannot be negative.');

        $upd = $conn->prepare('UPDATE products SET stock = ? WHERE id = ?');
        if (!$upd) throw new Exception('DB error updating stock.');
        $upd->bind_param('ii', $newStock, $product_id);
        if (!$upd->execute()) throw new Exception('Failed to update stock.');
        $upd->close();

        $log = $conn->prepare('INSERT INTO inventory_movements (product_id, change_qty, reason, order_id, admin_id) VALUES (?, ?, ?, NULL, ?)');
        if (!$log) throw new Exception('DB error logging movement.');
        $log->bind_param('iisi', $product_id, $delta, $reason, $admin_id);
        $log->execute();
        $log->close();

        $conn->commit();
        flash_set(true, 'Stock adjusted successfully.');
    } catch (Exception $e) {
        $conn->rollback();
        flash_set(false, $e->getMessage());
    }

    header('Location: inventory.php');
    exit();
}

// Filters
$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$low_stock = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';
$sort = $_GET['sort'] ?? 'newest'; // newest|stock_asc|stock_desc|name_asc|name_desc
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15; $offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    if (ctype_digit($search)) { $where[] = 'p.id = ?'; $types .= 'i'; $params[] = (int)$search; }
    else { $where[] = 'p.name LIKE ?'; $types .= 's'; $params[] = '%' . $search . '%'; }
}
if ($category_id > 0) { $where[] = 'p.category_id = ?'; $types .= 'i'; $params[] = $category_id; }
if ($low_stock) { $where[] = 'p.stock <= 5'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderMap = [
    'newest' => 'p.id DESC',
    'stock_asc' => 'p.stock ASC',
    'stock_desc' => 'p.stock DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
];
$orderBy = $orderMap[$sort] ?? $orderMap['newest'];

// Count
$total = 0;
$sqlCount = "SELECT COUNT(*) AS cnt FROM products p $whereSql";
if ($stmt = $conn->prepare($sqlCount)) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $total = (int)$row['cnt'];
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch page
$products = [];
$sql = "SELECT p.id, p.name, p.stock, p.price, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') { $bindTypes = $types . 'ii'; $bindParams = $params; $bindParams[] = $limit; $bindParams[] = $offset; $stmt->bind_param($bindTypes, ...$bindParams); }
    else { $stmt->bind_param('ii', $limit, $offset); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $products[] = $row; }
    $stmt->close();
}

function buildQuery(array $q): string { return http_build_query(array_filter($q, function($k){ return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); }
$baseQuery = buildQuery(['search'=>$search?:null,'category_id'=>$category_id?:null,'low_stock'=>$low_stock?'1':null,'sort'=>$sort!=='newest'?$sort:null]);

// Movement history (optional viewer)
$view_product = (int)($_GET['view_product'] ?? 0);
$movements = [];
if ($view_product > 0) {
    if ($stmt = $conn->prepare('SELECT id, change_qty, reason, order_id, admin_id, created_at FROM inventory_movements WHERE product_id = ? ORDER BY id DESC LIMIT 50')) {
        $stmt->bind_param('i', $view_product);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $movements[] = $row; }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Inventory Management</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css" />
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css" />
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Inventory</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
        </div>

        <?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success); ?></div><?php endif; ?>
        <?php if ($flash_error): ?><div class="alert alert-danger"><?= htmlspecialchars($flash_error); ?></div><?php endif; ?>

        <form method="get" class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" class="form-control" placeholder="Product name or ID" value="<?= htmlspecialchars($search); ?>" />
                </div>
                <div class="col-6 col-md-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id']; ?>" <?= $category_id===(int)$c['id']?'selected':''; ?>><?= htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="sort" class="form-label">Sort</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest" <?= $sort==='newest'?'selected':''; ?>>Newest</option>
                        <option value="stock_asc" <?= $sort==='stock_asc'?'selected':''; ?>>Stock: Low to High</option>
                        <option value="stock_desc" <?= $sort==='stock_desc'?'selected':''; ?>>Stock: High to Low</option>
                        <option value="name_asc" <?= $sort==='name_asc'?'selected':''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name: Z to A</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="low_stock" name="low_stock" value="1" <?= $low_stock?'checked':''; ?> />
                        <label for="low_stock" class="form-check-label">Low stock (<= 5)</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-admin-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="inventory.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="card mb-4">
            <div class="card-body p-0">
                <?php if (!empty($products)): ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?= (int)$p['id']; ?></td>
                                        <td><?= htmlspecialchars($p['name']); ?></td>
                                        <td><?= htmlspecialchars($p['category_name'] ?? ''); ?></td>
                                        <td class="text-end">
                                            <?php $badge = ((int)$p['stock'] <= 5) ? 'danger' : 'success'; ?>
                                            <span class="badge bg-<?= $badge; ?>"><?= (int)$p['stock']; ?></span>
                                        </td>
                                        <td class="text-end"><?= htmlspecialchars(number_format((float)$p['price'], 2)); ?> PKR</td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a href="inventory.php?<?= $baseQuery ? $baseQuery . '&' : '' ?>page=<?= (int)$page; ?>&view_product=<?= (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-clock-history me-1"></i> History</a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end p-2">
                                                    <li>
                                                        <form method="post" class="m-0 d-flex gap-2 align-items-center">
                                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                                            <input type="hidden" name="action" value="adjust_stock">
                                                            <input type="hidden" name="product_id" value="<?= (int)$p['id']; ?>">
                                                            <input type="number" name="delta" class="form-control form-control-sm" placeholder="±Qty" style="width: 110px" required>
                                                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason" style="width: 140px" value="adjustment">
                                                            <button type="submit" class="btn btn-sm btn-admin-primary">Apply</button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3">No products found.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mb-4" aria-label="Inventory pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':''; ?>"><a class="page-link" href="inventory.php?<?= $baseQuery; ?>&page=<?= max(1, $page-1); ?>">&laquo;</a></li>
                    <?php $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if ($start>1){echo '<li class="page-item"><a class="page-link" href="inventory.php?'.$baseQuery.'&page=1">1</a></li>'; if($start>2)echo '<li class="page-item disabled"><span class="page-link">…</span></li>';}
                    for($i=$start;$i<=$end;$i++){echo '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="inventory.php?'.$baseQuery.'&page='.$i.'">'.$i.'</a></li>';}
                    if ($end<$total_pages){if($end<$total_pages-1)echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="inventory.php?'.$baseQuery.'&page='.$total_pages.'">'.$total_pages.'</a></li>';}
                    ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>"><a class="page-link" href="inventory.php?<?= $baseQuery; ?>&page=<?= min($total_pages, $page+1); ?>">&raquo;</a></li>
                </ul>
            </nav>
        <?php endif; ?>

        <?php if ($view_product > 0): ?>
            <hr class="my-4">
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h2 class="h5 mb-0">Movement History · Product #<?= (int)$view_product; ?></h2>
                    <a href="inventory.php?<?= $baseQuery ? $baseQuery . '&' : '' ?>page=<?= (int)$page; ?>" class="btn btn-outline-secondary btn-sm">Close</a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($movements)): ?>
                        <div class="table-responsive">
                            <table class="table admin-table mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Change</th>
                                        <th>Reason</th>
                                        <th>Order</th>
                                        <th>Admin</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movements as $m): ?>
                                        <tr>
                                            <td><?= (int)$m['id']; ?></td>
                                            <td>
                                                <?php $cls = ((int)$m['change_qty'] < 0) ? 'text-danger' : 'text-success'; ?>
                                                <strong class="<?= $cls; ?>"><?= (int)$m['change_qty']; ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($m['reason']); ?></td>
                                            <td><?= $m['order_id'] ? ('#'.(int)$m['order_id']) : '-'; ?></td>
                                            <td><?= $m['admin_id'] ? (int)$m['admin_id'] : '-'; ?></td>
                                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($m['created_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3">No movements found for this product.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
