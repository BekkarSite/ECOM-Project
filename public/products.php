<!-- public/products.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/header.php';

// Fetch categories for filter
$categories = $conn->query("SELECT id, name FROM categories");

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($category_id) {
    $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
} else {
    $stmt = $conn->prepare("SELECT id, name, price, image FROM products");
}
$products = [];
if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}
?>
<main>
    <h1>Products</h1>
    <nav>
        <ul>
            <li><a href="products.php">All</a></li>
            <?php while ($cat = $categories->fetch_assoc()): ?>
            <li><a href="products.php?category_id=<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
            <?php endwhile; ?>
        </ul>
    </nav>
    <section id="product-list">
        <?php if ($products): foreach ($products as $product): ?>
            <div class="product">
                <a href="product.php?id=<?= htmlspecialchars($product['id']) ?>">
                    <img src="/assets/images/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                </a>
                <p><?= htmlspecialchars(number_format((float)$product['price'], 2)) ?> PKR</p>
                <a href="add_to_cart.php?product_id=<?= htmlspecialchars($product['id']) ?>&quantity=1">Add to Cart</a>
            </div>
        <?php endforeach; else: ?>
            <p>No products found.</p>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>