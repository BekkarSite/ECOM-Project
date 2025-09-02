<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch user information
$user = null;
if ($stmt = $conn->prepare('SELECT email, role, created_at FROM users WHERE id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

// Stats
$totalOrders = 0; $pendingOrders = 0; $completedOrders = 0; $totalSpent = 0.0;
if ($stmt = $conn->prepare("SELECT 
    SUM(status='pending') AS pending_cnt,
    SUM(status='completed') AS completed_cnt,
    COUNT(*) AS total_cnt,
    COALESCE(SUM(CASE WHEN status='completed' THEN total_price ELSE 0 END),0) AS total_spent
    FROM orders WHERE user_id = ?")) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $totalOrders = (int)$row['total_cnt'];
        $pendingOrders = (int)$row['pending_cnt'];
        $completedOrders = (int)$row['completed_cnt'];
        $totalSpent = (float)$row['total_spent'];
    }
    $stmt->close();
}

// Recent orders
$orders = [];
if ($stmt = $conn->prepare('SELECT id, total_price, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $orders[] = $row;
    }
    $stmt->close();
}
?>

<main class="py-4">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0">Your Dashboard</h1>
            <a href="profile.php" class="btn btn-outline-primary"><i class="fa fa-user me-1"></i> Profile & Settings</a>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5">Profile</h2>
                        <?php if ($user): ?>
                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-1"><strong>Role:</strong> <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-0 text-muted"><small>Member since: <?= htmlspecialchars(date('M j, Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8'); ?></small></p>
                        <?php else: ?>
                            <p class="text-danger mb-0">User information not found.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                        <a href="profile.php" class="btn btn-sm btn-primary"><i class="fa fa-cog me-1"></i> Manage Account</a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-8">
                <div class="row g-3">
                    <div class="col-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="text-muted small">Total Orders</div>
                                <div class="display-6 fw-bold"><?= (int)$totalOrders; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="text-muted small">Pending</div>
                                <div class="display-6 fw-bold text-warning"><?= (int)$pendingOrders; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="text-muted small">Completed</div>
                                <div class="display-6 fw-bold text-success"><?= (int)$completedOrders; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <div class="text-muted small">Total Spent</div>
                                <div class="h3 fw-bold text-primary"><?= htmlspecialchars(number_format($totalSpent, 2)); ?> PKR</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Recent Orders</h2>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Order #</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Total</th>
                                    <th scope="col" class="text-end">Action</th>
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
                    <div class="p-3">
                        <p class="mb-0">You have no orders yet. <a href="products.php">Browse products</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
