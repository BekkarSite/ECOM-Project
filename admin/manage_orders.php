<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
require_once __DIR__ . '/../config/db.php';

// CSRF token for POST actions
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_csrf'];

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $token = $_POST['csrf'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        $_SESSION['flash_error'] = 'Invalid CSRF token.';
        header('Location: manage_orders.php');
        exit();
    }
    if (!in_array($new_status, ['pending','completed','cancelled'], true)) {
        $_SESSION['flash_error'] = 'Invalid status value.';
        header('Location: manage_orders.php');
        exit();
    }
    if ($order_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid order id.';
        header('Location: manage_orders.php');
        exit();
    }

    if ($stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?")) {
        $stmt->bind_param('si', $new_status, $order_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Order #{$order_id} updated to {$new_status}.";
        } else {
            $_SESSION['flash_error'] = 'Failed to update order status.';
        }
        $stmt->close();
    }

    // Redirect back, keep filters if possible
    $redir = 'manage_orders.php';
    $qs = $_POST['redirect_query'] ?? '';
    if ($qs) { $redir .= '?' . $qs; }
    header('Location: ' . $redir);
    exit();
}

// Filters and pagination
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
$types  = '';

if (in_array($status, ['pending','completed','cancelled'], true)) {
    $where[] = 'o.status = ?';
    $types  .= 's';
    $params[] = $status;
}
if ($search !== '') {
    // search by order id (numeric) or by user email (if users table includes email)
    if (ctype_digit($search)) {
        $where[] = 'o.id = ?';
        $types  .= 'i';
        $params[] = (int)$search;
    } else {
        $where[] = 'u.email LIKE ?';
        $types  .= 's';
        $params[] = '%' . $search . '%';
    }
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$total = 0;
$sqlCount = "SELECT COUNT(*) AS cnt FROM orders o LEFT JOIN users u ON u.id = o.user_id $whereSql";
if ($stmt = $conn->prepare($sqlCount)) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $limit; }

// Fetch orders
$orders = [];
$sql = "SELECT o.id, o.user_id, u.email AS user_email, o.total_price, o.status, o.created_at
        FROM orders o LEFT JOIN users u ON u.id = o.user_id
        $whereSql
        ORDER BY o.id DESC
        LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $bindTypes = $types . 'ii';
        $bindParams = $params; $bindParams[] = $limit; $bindParams[] = $offset;
        $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $orders[] = $row; }
    $stmt->close();
}

// Optional: view order details if view_id is set
$view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
$view_order = null; $view_items = [];
if ($view_id > 0) {
    if ($stmt = $conn->prepare('SELECT o.id, o.user_id, u.email AS user_email, o.total_price, o.status, o.created_at FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?')) {
        $stmt->bind_param('i', $view_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $view_order = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($view_order) {
        if ($stmt = $conn->prepare('SELECT od.product_id, p.name, od.quantity, od.price, p.image FROM order_details od JOIN products p ON p.id = od.product_id WHERE od.order_id = ?')) {
            $stmt->bind_param('i', $view_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $view_items[] = $row; }
            $stmt->close();
        }
    }
}

function buildQuery(array $q): string {
    return http_build_query(array_filter($q, function($k){ return $k !== 'page'; }, ARRAY_FILTER_USE_KEY));
}
$baseQuery = buildQuery(['status' => $status !== '' ? $status : null, 'search' => $search !== '' ? $search : null]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Order Management</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>
<body>
<?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
<div class="admin-container">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <main class="content">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0">Order Management</h1>
            <a class="btn btn-outline-secondary" href="dashboard.php"><i class="fa fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>

        <form method="get" class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending"   <?= $status==='pending'?'selected':''; ?>>Pending</option>
                        <option value="completed" <?= $status==='completed'?'selected':''; ?>>Completed</option>
                        <option value="cancelled" <?= $status==='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label">Search (Order ID or Customer Email)</label>
                    <input id="search" name="search" class="form-control" value="<?= htmlspecialchars($search); ?>" placeholder="e.g., 1001 or customer@example.com" />
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button class="btn btn-admin-primary" type="submit"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <a class="btn btn-outline-secondary" href="manage_orders.php">Reset</a>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table admin-table mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td>#<?= (int)$o['id']; ?></td>
                                        <td><?= htmlspecialchars($o['user_email'] ?: (string)$o['user_id']); ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($o['created_at']))); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $o['status']==='completed'?'success':($o['status']==='pending'?'warning text-dark':'secondary'); ?>"><?= htmlspecialchars(ucfirst($o['status'])); ?></span>
                                        </td>
                                        <td class="text-end fw-semibold"><?= htmlspecialchars(number_format((float)$o['total_price'], 2)); ?> PKR</td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="manage_orders.php?<?= $baseQuery ? $baseQuery . '&' : '' ?>page=<?= (int)$page; ?>&view_id=<?= (int)$o['id']; ?>">
                                                <i class="fa fa-eye me-1"></i> View
                                            </a>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Update Status
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end p-2 shadow-sm">
                                                    <?php foreach (['pending','completed','cancelled'] as $st): if ($st === $o['status']) continue; ?>
                                                        <li>
                                                            <form method="post" class="m-0">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                                                <input type="hidden" name="order_id" value="<?= (int)$o['id']; ?>">
                                                                <input type="hidden" name="new_status" value="<?= $st; ?>">
                                                                <input type="hidden" name="redirect_query" value="<?= htmlspecialchars($baseQuery ? $baseQuery . '&page=' . (int)$page : 'page=' . (int)$page); ?>">
                                                                <button type="submit" class="dropdown-item">Mark as <?= ucfirst($st); ?></button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3">No orders found.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-3" aria-label="Orders pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':''; ?>">
                        <a class="page-link" href="manage_orders.php?<?= $baseQuery ?>&page=<?= max(1, $page-1); ?>">&laquo;</a>
                    </li>
                    <?php $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if ($start>1){echo '<li class="page-item"><a class="page-link" href="manage_orders.php?'.$baseQuery.'&page=1">1</a></li>'; if($start>2)echo '<li class="page-item disabled"><span class="page-link">…</span></li>';}
                    for($i=$start;$i<=$end;$i++){echo '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="manage_orders.php?'.$baseQuery.'&page='.$i.'">'.$i.'</a></li>';}
                    if ($end<$total_pages){if($end<$total_pages-1)echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="manage_orders.php?'.$baseQuery.'&page='.$total_pages.'">'.$total_pages.'</a></li>';} ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                        <a class="page-link" href="manage_orders.php?<?= $baseQuery ?>&page=<?= min($total_pages, $page+1); ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

        <?php if ($view_order): ?>
            <hr class="my-4">
            <div id="order-details">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h5 mb-0">Order #<?= (int)$view_order['id']; ?> Details</h2>
                    <div class="d-flex gap-2">
                        <?php foreach (['pending','completed','cancelled'] as $st): if ($st === $view_order['status']) continue; ?>
                            <form method="post" class="m-0">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="order_id" value="<?= (int)$view_order['id']; ?>">
                                <input type="hidden" name="new_status" value="<?= $st; ?>">
                                <input type="hidden" name="redirect_query" value="<?= htmlspecialchars(($baseQuery? $baseQuery . '&' : '') . 'page=' . (int)$page . '&view_id=' . (int)$view_order['id']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark as <?= ucfirst($st); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($view_order['user_email'] ?: (string)$view_order['user_id']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars(date('M j, Y g:i A', strtotime($view_order['created_at']))); ?></p>
                                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?php echo $view_order['status']==='completed'?'success':($view_order['status']==='pending'?'warning text-dark':'secondary'); ?>"><?= htmlspecialchars(ucfirst($view_order['status'])); ?></span></p>
                                <p class="mb-0"><strong>Total:</strong> <span class="text-primary fw-bold"><?= htmlspecialchars(number_format((float)$view_order['total_price'], 2)); ?> PKR</span></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-8">
                        <div class="card h-100">
                            <div class="card-body p-0">
                                <?php if (empty($view_items)): ?>
                                    <div class="p-3">No items found for this order.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table admin-table mb-0 align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Product</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Unit Price</th>
                                                    <th class="text-end">Line Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($view_items as $it): $line = (float)$it['price'] * (int)$it['quantity']; ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php $img = $it['image'] ? '../assets/images/' . $it['image'] : '../assets/images/placeholder.svg'; ?>
                                                                <img src="<?= htmlspecialchars($img); ?>" alt="<?= htmlspecialchars($it['name']); ?>" style="width:40px;height:40px;object-fit:contain;" class="me-2 rounded bg-light p-1">
                                                                <div>
                                                                    <div class="fw-semibold"><?= htmlspecialchars($it['name']); ?></div>
                                                                    <small class="text-muted">ID: <?= (int)$it['product_id']; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center"><?= (int)$it['quantity']; ?></td>
                                                        <td class="text-end"><?= htmlspecialchars(number_format((float)$it['price'], 2)); ?> PKR</td>
                                                        <td class="text-end fw-semibold"><?= htmlspecialchars(number_format($line, 2)); ?> PKR</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>
</html>
