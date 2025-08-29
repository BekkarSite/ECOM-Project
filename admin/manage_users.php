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
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <h1>Manage Users</h1>
            <p>Placeholder page for user registration and management.</p>
        </main>
    </div>
</body>
</html>