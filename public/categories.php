<!-- public/categories.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

$categories = $conn->query("SELECT id, name FROM categories");
?>
<main>
    <h1>Categories</h1>
    <ul>
        <?php while ($row = $categories->fetch_assoc()): ?>
            <li><a href="products.php?category_id=<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['name']) ?></a></li>
        <?php endwhile; ?>
    </ul>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>