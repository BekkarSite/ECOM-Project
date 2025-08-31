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
    <title>Payment Methods</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
</head>

<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="admin-container">
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        <main class="content">
            <h1>Payment Methods</h1>
            <p>Placeholder page for managing payment options.</p>
        </main>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>