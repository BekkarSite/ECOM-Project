<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// CSRF token (shared with inventory.php)
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Filters
$search      = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$min_price   = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price   = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sort        = $_GET['sort'] ?? 'newest'; // newest|price_asc|price_desc|name_asc|name_desc|stock_asc|stock_desc
$page        = max(1, (int)($_GET['page'] ?? 1));
$limit       = 15;
$offset      = ($page - 1) * $limit;

// Fetch categories for filters
$categories = [];
if ($res = $conn->query('SELECT id, name FROM categories ORDER BY name ASC')) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
}

// Build WHERE
$where = [];
$types = '';
$params = [];
if ($search !== '') {
    if (ctype_digit($search)) { $where[] = 'p.id = ?'; $types .= 'i'; $params[] = (int)$search; }
    else { $where[] = 'p.name LIKE ?'; $types .= 's'; $params[] = '%' . $search . '%'; }
}
if ($category_id > 0) { $where[] = 'p.category_id = ?'; $types .= 'i'; $params[] = $category_id; }
if ($min_price !== null) { $where[] = 'p.price >= ?'; $types .= 'd'; $params[] = $min_price; }
if ($max_price !== null) { $where[] = 'p.price <= ?'; $types .= 'd'; $params[] = $max_price; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderMap = [
    'newest'     => 'p.id DESC',
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc'   => 'p.name ASC',
    'name_desc'  => 'p.name DESC',
    'stock_asc'  => 'p.stock ASC',
    'stock_desc' => 'p.stock DESC',
];
$orderBy = $orderMap[$sort] ?? $orderMap['newest'];

// Count total
$total = 0;
$sqlCount = "SELECT COUNT(*) AS cnt FROM products p $whereSql";
if ($stmt = $conn->prepare($sqlCount)) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch page
$products = [];
$sql = "SELECT p.id, p.name, p.price, p.stock, p.image, p.created_at, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON c.id = p.category_id 
        $whereSql 
        ORDER BY $orderBy 
        LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $bindTypes = $types . 'ii';
        $bindParams = $params;
        $bindParams[] = $limit;
        $bindParams[] = $offset;
        $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $products[] = $row; }
    $stmt->close();
}

function q(array $q): string { return http_build_query(array_filter($q, function($v){ return $v !== null && $v !== '' && $v !== false; })); }
$baseQuery = q([
    'search' => $search ?: null,
    'category_id' => $category_id ?: null,
    'min_price' => $min_price !== null ? $min_price : null,
    'max_price' => $max_price !== null ? $max_price : null,
    'sort' => $sort !== 'newest' ? $sort : null,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Products</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css" />
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css" />
    <link rel="stylesheet" href="../assets/css/custom/manageproductsstyle.css" />
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Products</h1>
            <div class="d-flex gap-2">
                <a href="inventory.php" class="btn btn-outline-secondary"><i class="bi bi-inboxes me-1"></i> Inventory</a>
                <a href="add_product.php" class="btn btn-admin-primary"><i class="bi bi-plus-lg me-1"></i> Add Product</a>
            </div>
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
                    <label for="min_price" class="form-label">Min Price</label>
                    <input type="number" step="0.01" id="min_price" name="min_price" class="form-control" value="<?= $min_price !== null ? htmlspecialchars((string)$min_price) : '' ?>" />
                </div>
                <div class="col-6 col-md-2">
                    <label for="max_price" class="form-label">Max Price</label>
                    <input type="number" step="0.01" id="max_price" name="max_price" class="form-control" value="<?= $max_price !== null ? htmlspecialchars((string)$max_price) : '' ?>" />
                </div>
                <div class="col-6 col-md-3">
                    <label for="sort" class="form-label">Sort</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest" <?= $sort==='newest'?'selected':''; ?>>Newest</option>
                        <option value="price_asc" <?= $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?= $sort==='name_asc'?'selected':''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name: Z to A</option>
                        <option value="stock_asc" <?= $sort==='stock_asc'?'selected':''; ?>>Stock: Low to High</option>
                        <option value="stock_desc" <?= $sort==='stock_desc'?'selected':''; ?>>Stock: High to Low</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-admin-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="manage_products.php" class="btn btn-outline-secondary">Reset</a>
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
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Price</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?= (int)$p['id']; ?></td>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="../assets/images/<?= htmlspecialchars($p['image']); ?>" alt="<?= htmlspecialchars($p['name']); ?>" style="width:48px;height:48px;object-fit:cover;border-radius:4px;" />
                                            <?php else: ?>
                                                <div style="width:48px;height:48px;background:#eee;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;">—</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($p['name']); ?></td>
                                        <td><?= htmlspecialchars($p['category_name'] ?? ''); ?></td>
                                        <td class="text-end">
                                            <?php $badge = ((int)$p['stock'] <= 5) ? 'danger' : 'success'; ?>
                                            <span class="badge bg-<?= $badge; ?>"><?= (int)$p['stock']; ?></span>
                                        </td>
                                        <td class="text-end"><?= htmlspecialchars(number_format((float)$p['price'], 2)); ?> PKR</td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($p['created_at']))); ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="edit_product.php?id=<?= (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                <form method="post" action="delete_product.php" onsubmit="return confirm('Delete this product? This cannot be undone.');" class="d-inline">
                                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>" />
                                                    <input type="hidden" name="id" value="<?= (int)$p['id']; ?>" />
                                                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']); ?>" />
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
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
            <nav class="mb-4" aria-label="Products pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':''; ?>">
                        <a class="page-link" href="manage_products.php?<?= $baseQuery; ?>&page=<?= max(1, $page-1); ?>" aria-label="Previous">&laquo;</a>
                    </li>
                    <?php $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if ($start>1){echo '<li class="page-item"><a class="page-link" href="manage_products.php?'.$baseQuery.'&page=1">1</a></li>'; if($start>2)echo '<li class="page-item disabled"><span class="page-link">…</span></li>';}
                    for($i=$start;$i<=$end;$i++){echo '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="manage_products.php?'.$baseQuery.'&page='.$i.'">'.$i.'</a></li>';}
                    if ($end<$total_pages){if($end<$total_pages-1)echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="manage_products.php?'.$baseQuery.'&page='.$total_pages.'">'.$total_pages.'</a></li>';}
                    ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                        <a class="page-link" href="manage_products.php?<?= $baseQuery; ?>&page=<?= min($total_pages, $page+1); ?>" aria-label="Next">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
