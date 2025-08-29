<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user information
$userStmt = $conn->prepare('SELECT email, role FROM users WHERE id = ?');
$userStmt->bind_param('i', $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult ? $userResult->fetch_assoc() : null;
$userStmt->close();

// Fetch total orders count
$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM orders WHERE user_id = ?');
$countStmt->bind_param('i', $user_id);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalOrders = $countResult ? $countResult->fetch_assoc()['total'] : 0;
$countStmt->close();

// Fetch all orders for the user
$orderStmt = $conn->prepare('SELECT id, total_price, status FROM orders WHERE user_id = ? ORDER BY id DESC');
$orderStmt->bind_param('i', $user_id);
$orderStmt->execute();
$orders = $orderStmt->get_result();
?>
<link rel="stylesheet" href="../assets/css/dashboardstyle.css">

<main class="dashboard-wrapper">
    <section class="profile">
        <h2>Your Profile</h2>
        <?php if ($user): ?>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Role:</strong> <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
            <p>User information not found.</p>
        <?php endif; ?>
    </section>

    <section class="summary">
        <h2>Order Summary</h2>
        <p><strong>Total Orders:</strong> <?= (int)$totalOrders; ?></p>
    </section>

    <section class="orders">
        <h2>Your Orders</h2>
        <?php if ($orders && $orders->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Total Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?= $order['id']; ?></td>
                            <td><?= number_format($order['total_price'], 2); ?> PKR</td>
                            <td><?= htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You have no orders yet.</p>
        <?php endif; ?>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>