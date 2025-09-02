<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$where = 'WHERE user_id = ?';
$params = [$user_id];
$types = 'i';
if (in_array($status, ['pending', 'completed', 'cancelled'], true)) {
    $where .= ' AND status = ?';
    $params[] = $status;
    $types .= 's';
}

// Count total
$total = 0;
if ($stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders $where")) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $total = (int)$row['cnt']; }
    $stmt->close();
}
$total_pages = max(1, (int)ceil($total / $limit));

$orders = [];
if ($stmt = $conn->prepare("SELECT id, total_price, status, created_at FROM orders $where ORDER BY id DESC LIMIT ? OFFSET ?")) {
    $types2 = $types . 'ii';
    $params2 = $params; $params2[] = $limit; $params2[] = $offset;
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $orders[] = $row; }
    $stmt->close();
}

function buildQuery($q) {
    return http_build_query(array_filter($q, function($k){ return $k !== 'page'; }, ARRAY_FILTER_USE_KEY));
}
$baseQuery = buildQuery(['status' => $status !== '' ? $status : null]);
?>
<main class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0">Your Orders</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-1"></i> Back to Dashboard</a>
        </div>

        <form method="get" class="card card-body mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" <?= $status==='pending'?'selected':''; ?>>Pending</option>
                        <option value="completed" <?= $status==='completed'?'selected':''; ?>>Completed</option>
                        <option value="cancelled" <?= $status==='cancelled'?'selected':''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><?= (int)$o['id']; ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($o['created_at']))); ?></td>
                                        <td><span class="badge bg-<?php echo $o['status']==='completed'?'success':($o['status']==='pending'?'warning text-dark':'secondary'); ?>"><?= htmlspecialchars(ucfirst($o['status'])); ?></span></td>
                                        <td class="text-end"><?= htmlspecialchars(number_format((float)$o['total_price'], 2)); ?> PKR</td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="order.php?id=<?= (int)$o['id']; ?>">
                                                <i class="fa fa-eye me-1"></i> View
                                            </a>
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
            <nav class="mt-4" aria-label="Orders pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page<=1?'disabled':''; ?>">
                        <a class="page-link" href="orders.php?<?= $baseQuery ?>&page=<?= max(1, $page-1); ?>">&laquo;</a>
                    </li>
                    <?php $start=max(1,$page-2);$end=min($total_pages,$page+2);
                    if ($start>1){echo '<li class="page-item"><a class="page-link" href="orders.php?'.$baseQuery.'&page=1">1</a></li>'; if($start>2)echo '<li class="page-item disabled"><span class="page-link">…</span></li>';}
                    for($i=$start;$i<=$end;$i++){echo '<li class="page-item '.($i===$page?'active':'').'"><a class="page-link" href="orders.php?'.$baseQuery.'&page='.$i.'">'.$i.'</a></li>';} 
                    if ($end<$total_pages){if($end<$total_pages-1)echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="orders.php?'.$baseQuery.'&page='.$total_pages.'">'.$total_pages.'</a></li>';} ?>
                    <li class="page-item <?= $page>=$total_pages?'disabled':''; ?>">
                        <a class="page-link" href="orders.php?<?= $baseQuery ?>&page=<?= min($total_pages, $page+1); ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
