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
    <title>Edit User Data</title>
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        <main class="content">
            <h1>Edit User Data</h1>
            <p>Placeholder page for editing user information.</p>
        </main>
    </div>
</body>
</html>