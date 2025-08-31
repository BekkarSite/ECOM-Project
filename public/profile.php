<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<main>
    <h1>User Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['email'], ENT_QUOTES, 'UTF-8'); ?>!</p>
    <nav>
        <ul>
            <li><a href="cart.php">View Cart</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</main>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>