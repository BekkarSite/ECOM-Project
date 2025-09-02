<!-- public/products.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

// Fetch categories for filter dropdown
$categoriesRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = [];
if ($categoriesRes) {
    while ($row = $categoriesRes->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Inputs
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$price_min = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (float)$_GET['price_min'] : null;
$price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (float)$_GET['price_max'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Sanity: if min > max, swap
if ($price_min !== null && $price_max !== null && $price_min > $price_max) {
    $tmp = $price_min;
    $price_min = $price_max;
    $price_max = $tmp;
}

// Sorting map (whitelist)
$sortMap = [
    'newest' => 'p.id DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
];
$orderBy = isset($sortMap[$sort]) ? $sortMap[$sort] : $sortMap['newest'];

$where = [];
$params = [];
$types = '';

if ($category_id > 0) {
    $where[] = 'p.category_id = ?';
    $types .= 'i';
    $params[] = $category_id;
}
if ($price_min !== null) {
    $where[] = 'p.price >= ?';
    $types .= 'd';
    $params[] = $price_min;
}
if ($price_max !== null) {
    $where[] = 'p.price <= ?';
    $types .= 'd';
    $params[] = $price_max;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Helper to bind params by reference for call_user_func_array
function bindParams($stmt, $types, $params) {
    if ($types === '') return; // nothing to bind
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Count total for pagination
$total = 0;
$countSql = "SELECT COUNT(*) as cnt FROM products p $whereSql";
if ($stmtCnt = $conn->prepare($countSql)) {
    bindParams($stmtCnt, $types, $params);
    if ($stmtCnt->execute()) {
        $resCnt = $stmtCnt->get_result();
        if ($row = $resCnt->fetch_assoc()) {
            $total = (int)$row['cnt'];
        }
    }
    $stmtCnt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch products page
$sql = "SELECT p.id, p.name, p.price, p.image FROM products p $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?";
$products = [];
if ($stmt = $conn->prepare($sql)) {
    $bindTypes = $types . 'ii';
    $bindParams = $params;
    $bindParams[] = $limit;
    $bindParams[] = $offset;

    // Build refs for binding
    $refs = [];
    foreach ($bindParams as $k => $v) { $refs[$k] = &$bindParams[$k]; }
    array_unshift($refs, $bindTypes);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt->close();
}

// Helper: persist query params except page
function buildQuery($params) {
    return http_build_query(array_filter($params, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY));
}

$baseQuery = buildQuery([
    'category_id' => $category_id ?: null,
    'price_min' => $price_min !== null ? $price_min : null,
    'price_max' => $price_max !== null ? $price_max : null,
    'sort' => $sort,
]);
?>
<main class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h3 mb-0">Products</h1>
            <span class="text-muted small"><?php echo (int)$total; ?> item(s) found</span>
        </div>

        <form class="card card-body mb-4" method="get" action="products.php">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $category_id === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="price_min" class="form-label">Min Price</label>
                    <input type="number" step="0.01" min="0" id="price_min" name="price_min" class="form-control" value="<?= $price_min !== null ? htmlspecialchars($price_min) : '' ?>" placeholder="0">
                </div>
                <div class="col-6 col-md-2">
                    <label for="price_max" class="form-label">Max Price</label>
                    <input type="number" step="0.01" min="0" id="price_max" name="price_max" class="form-control" value="<?= $price_max !== null ? htmlspecialchars($price_max) : '' ?>" placeholder="9999">
                </div>
                <div class="col-12 col-md-2">
                    <label for="sort" class="form-label">Sort by</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest" <?= $sort==='newest'?'selected':''; ?>>Newest</option>
                        <option value="price_asc" <?= $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                        <option value="name_asc" <?= $sort==='name_asc'?'selected':''; ?>>Name: A to Z</option>
                        <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name: Z to A</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </div>
        </form>

        <section id="product-list">
            <?php if ($products): ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($products as $product): ?>
                        <div class="col">
                            <div class="card product-card h-100">
                                <a href="product.php?id=<?= (int)$product['id'] ?>" class="text-decoration-none text-dark">
                                    <div class="ratio ratio-1x1 product-thumb bg-light">
                                        <?php $img = $product['image'] ? '../assets/images/' . $product['image'] : '../assets/images/placeholder.svg'; ?>
                                        <img src="<?= htmlspecialchars($img) ?>" class="card-img-top object-fit-contain p-3" alt="<?= htmlspecialchars($product['name']) ?>">
                                    </div>
                                    <div class="card-body">
                                        <h3 class="card-title h6 mb-2 text-truncate" title="<?= htmlspecialchars($product['name']) ?>"><?= htmlspecialchars($product['name']) ?></h3>
                                        <p class="card-text fw-bold text-primary mb-0"><?= htmlspecialchars(number_format((float)$product['price'], 2)) ?> PKR</p>
                                    </div>
                                </a>
                                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                                    <a href="add_to_cart.php?product_id=<?= (int)$product['id'] ?>&quantity=1" class="btn btn-outline-primary w-100 add-to-cart">
                                        <i class="fa fa-cart-plus me-1"></i> Add to Cart
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No products found for the selected filters.
                </div>
            <?php endif; ?>
        </section>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-4" aria-label="Product pagination">
                <ul class="pagination justify-content-center">
                    <?php $prevDisabled = $page <= 1 ? ' disabled' : ''; ?>
                    <li class="page-item<?= $prevDisabled ?>">
                        <a class="page-link" href="products.php?<?= $baseQuery ?>&page=<?= max(1, $page-1) ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <?php
                    // Show up to 5 pages around current
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1) {
                        echo '<li class="page-item"><a class="page-link" href="products.php?' . $baseQuery . '&page=1">1</a></li>';
                        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    for ($i = $start; $i <= $end; $i++) {
                        $active = $i === $page ? ' active' : '';
                        echo '<li class="page-item' . $active . '"><a class="page-link" href="products.php?' . $baseQuery . '&page=' . $i . '">' . $i . '</a></li>';
                    }
                    if ($end < $total_pages) {
                        if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="products.php?' . $baseQuery . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                    }
                    ?>

                    <?php $nextDisabled = $page >= $total_pages ? ' disabled' : ''; ?>
                    <li class="page-item<?= $nextDisabled ?>">
                        <a class="page-link" href="products.php?<?= $baseQuery ?>&page=<?= min($total_pages, $page+1) ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
