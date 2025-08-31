<?php
session_start();

// Ensure only authenticated admins can access the dashboard
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Include the database connection to fetch summary statistics
include('../../config/db.php');

// Helper function to fetch counts from a table
function getCount(mysqli $conn, string $table): int
{
    $sql = "SELECT COUNT(*) AS count FROM {$table}";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return (int) $row['count'];
    }
    return 0;
}

$productCount  = getCount($conn, 'products');
$orderCount    = getCount($conn, 'orders');
$userCount     = getCount($conn, 'users');
$categoryCount = getCount($conn, 'categories');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/admindashboard.css">
</head>

<body>
    <?php require_once __DIR__ . '/header.php'; ?>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <h1>Admin Dashboard</h1>
            <p>Welcome, <?= htmlspecialchars($_SESSION['email'], ENT_QUOTES, 'UTF-8'); ?>!</p>
            <div class="dashboard-stats">
                <div class="stat">
                    <span class="stat-number"><?= htmlspecialchars($productCount); ?></span>
                    <span class="stat-label">Products</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= htmlspecialchars($categoryCount); ?></span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= htmlspecialchars($orderCount); ?></span>
                    <span class="stat-label">Orders</span>
                </div>
                <div class="stat">
                    <span class="stat-number"><?= htmlspecialchars($userCount); ?></span>
                    <span class="stat-label">Users</span>
                </div>
            </div>
        </main>
    </div>
    <?php require_once __DIR__ . '/footer.php'; ?>
</body>

</html>