<!-- public/index.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';
?>

<main>
    <h1>Welcome to Our eCommerce Site</h1>
    <section id="featured-products">
        <h2>Featured Products</h2>
        <?php
        $stmt = $conn->prepare("SELECT id, name, price, image FROM products LIMIT 4");
        if ($stmt && $stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $id = htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8');
                    $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                    $price = htmlspecialchars(number_format((float)$row['price'], 2), ENT_QUOTES, 'UTF-8');
                    $image = htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8');
        ?>
                    <div class="product">
                        <img src="../assets/images/<?= $image ?>" alt="<?= $name ?>">
                        <h3><?= $name ?></h3>
                        <p><?= $price ?> PKR</p>
                        <a href="add_to_cart.php?product_id=<?= $id ?>&quantity=1">Add to Cart</a>
                    </div>
        <?php
                }
            } else {
                echo '<p>No products available.</p>';
            }
            $stmt->close();
        } else {
            echo '<p>Error loading products.</p>';
        }
        ?>
    </section>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>