<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order Management</title>
    <link rel="stylesheet" href="../assets/css/custom/typography.css">
    <link rel="stylesheet" href="../assets/css/custom/admindashboard.css">
</head>

<body>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_header.php'; ?>
    <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h1>Order Management</h1>
            <p>Placeholder page for handling orders.</p>
        </main>
    </div>
    <?php require_once __DIR__ . '/../app/includes/admin/admin_footer.php'; ?>
</body>

</html>
