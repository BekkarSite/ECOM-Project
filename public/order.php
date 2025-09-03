<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$order = null;
$items = [];

if ($order_id > 0) {
    if ($stmt = $conn->prepare('SELECT id, user_id, total_price, status, created_at FROM orders WHERE id = ? AND user_id = ?')) {
        $stmt->bind_param('ii', $order_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($order) {
        if ($stmt = $conn->prepare('SELECT od.product_id, p.name, od.quantity, od.price, p.image FROM order_details od JOIN products p ON p.id = od.product_id WHERE od.order_id = ?')) {
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $items[] = $row; }
            $stmt->close();
        }
    }
}
?>
<main class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Order #<?= htmlspecialchars($order_id); ?></li>
                </ol>
            </nav>
            <a href="orders.php" class="btn btn-outline-secondary"><i class="fa fa-list me-1"></i> All Orders</a>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>

        <?php if (!$order): ?>
            <div class="alert alert-danger">Order not found or you do not have permission to view this order.</div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-12 col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-white"><h2 class="h5 mb-0">Order Summary</h2></div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Order #:</strong> <?= (int)$order['id']; ?></p>
                            <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))); ?></p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?php echo $order['status']==='completed'?'success':($order['status']==='pending'?'warning text-dark':'secondary'); ?>"><?= htmlspecialchars(ucfirst($order['status'])); ?></span></p>
                            <p class="mb-0"><strong>Total:</strong> <span class="text-primary fw-bold"><?= htmlspecialchars(number_format((float)$order['total_price'], 2)); ?> PKR</span></p>
                        </div>
                        <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                            <form method="post" action="reorder.php" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-cart-plus me-1"></i> Reorder</button>
                            </form>
                            <?php if ($order['status'] === 'pending'): ?>
                                <form method="post" action="cancel-order" class="m-0" onsubmit="return confirm('Cancel this pending order?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger"><i class="fa fa-ban me-1"></i> Cancel</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-8">
                    <div class="card h-100">
                        <div class="card-header bg-white"><h2 class="h5 mb-0">Items</h2></div>
                        <div class="card-body p-0">
                            <?php if (empty($items)): ?>
                                <div class="p-3">No items found in this order.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Unit Price</th>
                                                <th class="text-end">Line Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $it): $line = (float)$it['price'] * (int)$it['quantity']; ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php $img = $it['image'] ? ($BASE_PATH . '/assets/images/' . $it['image']) : ($BASE_PATH . '/assets/images/placeholder.svg'); ?>
                                                            <img src="<?= htmlspecialchars($img); ?>" alt="<?= htmlspecialchars($it['name']); ?>" style="width:48px;height:48px;object-fit:contain;" class="me-2 rounded bg-light p-1">
                                                            <div>
                                                                <div class="fw-semibold"><a href="product.php?id=<?= (int)$it['product_id']; ?>" class="text-decoration-none"><?= htmlspecialchars($it['name']); ?></a></div>
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
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
