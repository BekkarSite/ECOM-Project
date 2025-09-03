<?php
// admin/manage_categories.php (Redesigned)
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// CSRF token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// Flash helpers
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
function flash_set($ok, $msg) { $_SESSION[$ok ? 'flash_success' : 'flash_error'] = $msg; }

// Handle POST actions: create/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        flash_set(false, 'Invalid CSRF token.');
        header('Location: manage_categories.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name === '' || mb_strlen($name) > 255) {
            flash_set(false, 'Provide a valid category name (1-255 chars).');
            header('Location: manage_categories.php');
            exit();
        }
        // Duplicate check (case-insensitive depending on collation, keep explicit)
        if ($stmt = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1')) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->fetch_assoc()) {
                $stmt->close();
                flash_set(false, 'Category with this name already exists.');
                header('Location: manage_categories.php');
                exit();
            }
            $stmt->close();
        }
        if ($stmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)')) {
            $stmt->bind_param('ss', $name, $description);
            if ($stmt->execute()) {
                flash_set(true, 'Category added successfully.');
            } else {
                flash_set(false, 'Error adding category: ' . $conn->error);
            }
            $stmt->close();
        } else {
            flash_set(false, 'DB error while preparing insert.');
        }
        header('Location: manage_categories.php');
        exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set(false, 'Invalid category.');
            header('Location: manage_categories.php');
            exit();
        }
        // Block deletion if products exist
        $prodCount = 0;
        if ($stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM products WHERE category_id = ?')) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) { $prodCount = (int)$row['cnt']; }
            $stmt->close();
        }
        if ($prodCount > 0) {
            flash_set(false, 'Cannot delete: there are ' . $prodCount . ' product(s) in this category. Reassign or delete those products first.');
            header('Location: manage_categories.php');
            exit();
        }
        if ($stmt = $conn->prepare('DELETE FROM categories WHERE id = ?')) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) { flash_set(true, 'Category deleted.'); }
            else { flash_set(false, 'Error deleting category: ' . $conn->error); }
            $stmt->close();
        } else {
            flash_set(false, 'DB error while preparing delete.');
        }
        header('Location: manage_categories.php');
        exit();
    }

    // Unknown action
    flash_set(false, 'Unknown action.');
    header('Location: manage_categories.php');
    exit();
}

// Filters
$q     = trim($_GET['q'] ?? '');
$sort  = $_GET['sort'] ?? 'newest'; // newest|name_asc|name_desc|products_desc|products_asc
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15; $offset = ($page - 1) * $limit;

// Build WHERE
$where = [];$types='';$params=[];
if ($q !== '') { $where[] = 'c.name LIKE ?'; $types .= 's'; $params[] = '%' . $q . '%'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$orderMap = [
    'newest'        => 'c.id DESC',
    'name_asc'      => 'c.name ASC',
    'name_desc'     => 'c.name DESC',
    'products_desc' => 'product_count DESC, c.name ASC',
    'products_asc'  => 'product_count ASC, c.name ASC',
];
$orderBy = $orderMap[$sort] ?? $orderMap['newest'];

// Count total
$total = 0;
$sqlCount = "SELECT COUNT(*) AS cnt FROM categories c $whereSql";
if ($stmt = $conn->prepare($sqlCount)) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch page rows with product counts
$categories = [];
$sql = "SELECT c.id, c.name, c.description, c.created_at,
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
        FROM categories c
        $whereSql
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') { $bindTypes = $types . 'ii'; $bindParams = $params; $bindParams[] = $limit; $bindParams[] = $offset; $stmt->bind_param($bindTypes, ...$bindParams); }
    else { $stmt->bind_param('ii', $limit, $offset); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
    $stmt->close();
}

function qbuild(array $q): string { return http_build_query(array_filter($q, fn($v)=>$v!==null&&$v!==''&&$v!==false)); }
$baseQuery = qbuild([
    'q' => $q ?: null,
    'sort' => $sort !== 'newest' ? $sort : null,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Categories</h1>
            <a href="manage_products.php" class="btn btn-outline-secondary"><i class="fa fa-box me-1"></i> Products</a>
        </div>

        <?php if ($flash_success): ?><div class="alert alert-success"><?= htmlspecialchars($flash_success); ?></div><?php endif; ?>
        <?php if ($flash_error): ?><div class="alert alert-danger"><?= htmlspecialchars($flash_error); ?></div><?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-8">
                <form method="get" class="card card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6">
                            <label for="q" class="form-label">Search</label>
                            <input id="q" name="q" class="form-control" placeholder="Category name" value="<?= htmlspecialchars($q); ?>" />
                        </div>
                        <div class="col-6 col-md-4">
                            <label for="sort" class="form-label">Sort</label>
                            <select id="sort" name="sort" class="form-select">
                                <option value="newest" <?= $sort==='newest'?'selected':''; ?>>Newest</option>
                                <option value="name_asc" <?= $sort==='name_asc'?'selected':''; ?>>Name: A to Z</option>
                                <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name: Z to A</option>
                                <option value="products_desc" <?= $sort==='products_desc'?'selected':''; ?>>Products: High to Low</option>
                                <option value="products_asc" <?= $sort==='products_asc'?'selected':''; ?>>Products: Low to High</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-admin-primary"><i class="fa fa-filter me-1"></i> Filter</button>
                            <a href="manage_categories.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-12 col-lg-4">
                <form method="post" class="card card-body">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>" />
                    <input type="hidden" name="action" value="create" />
                    <h2 class="h6 mb-3">Add Category</h2>
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input id="name" name="name" class="form-control" maxlength="255" required />
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Optional"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-admin-primary"><i class="fa fa-plus me-1"></i> Add</button>
                        <button type="reset" class="btn btn-outline-secondary">Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body p-0">
                <?php if (!empty($categories)): ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th class="text-end">Products</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $c): ?>
                                    <tr>
                                        <td><?= (int)$c['id']; ?></td>
                                        <td><?= htmlspecialchars($c['name']); ?></td>
                                        <td><?= htmlspecialchars(mb_strimwidth((string)($c['description'] ?? ''), 0, 80, strlen((string)($c['description'] ?? ''))>80?'…':'')); ?></td>
                                        <td class="text-end">
                                            <?php $cnt = (int)$c['product_count']; $badge = $cnt>0 ? 'primary' : 'secondary'; ?>
                                            <span class="badge bg-<?= $badge; ?>"><?= $cnt; ?></span>
                                        </td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($c['created_at']))); ?></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="edit_category.php?id=<?= (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-pen"></i></a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this category? This cannot be undone.');">
                                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>" />
                                                    <input type="hidden" name="action" value="delete" />
                                                    <input type="hidden" name="id" value="<?= (int)$c['id']; ?>" />
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" <?= ((int)$c['product_count']>0)?'disabled title="Cannot delete: category has products"':''; ?>><i class="fa fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3">No categories found.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mb-4" aria-label="Categories pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':''; ?>">
                        <a class="page-link" href="manage_categories.php?<?= $baseQuery; ?>&page=<?= max(1, $page-1); ?>" aria-label="Previous">&laquo;</a>
                    </li>
                    <?php $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if ($start>1){echo '<li class="page-item"><a class="page-link" href="manage_categories.php?'.$baseQuery.'&page=1">1</a></li>'; if($start>2)echo '<li class="page-item disabled"><span class="page-link">…</span></li>';} 
                    for($i=$start;$i<=$end;$i++){echo '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="manage_categories.php?'.$baseQuery.'&page='.$i.'">'.$i.'</a></li>';}
                    if ($end<$total_pages){if($end<$total_pages-1)echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="manage_categories.php?'.$baseQuery.'&page='.$total_pages.'">'.$total_pages.'</a></li>';} ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                        <a class="page-link" href="manage_categories.php?<?= $baseQuery; ?>&page=<?= min($total_pages, $page+1); ?>" aria-label="Next">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>