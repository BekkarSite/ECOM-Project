<!-- public/product.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

if (!isset($_GET['id'])) {
    echo "<p>Product not found.</p>";
} else {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT name, description, price, image FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        echo "<p>Product not found.</p>";
    } else {
?>
        <main>
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <img src="../assets/images/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            <p><?= htmlspecialchars(number_format((float)$product['price'], 2)) ?> PKR</p>
            <a href="add_to_cart.php?product_id=<?= $id ?>&quantity=1">Add to Cart</a>
        </main>
<?php
    }
}
require_once __DIR__ . '/../app/includes/public/public_footer.php';
?>