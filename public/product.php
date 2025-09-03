<!-- public/product.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

$product = null;
$related = [];

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $conn->prepare("SELECT id, category_id, name, description, price, image FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($product && !empty($product['category_id'])) {
        // Fetch related products from the same category
        $catId = (int)$product['category_id'];
        $stmtRel = $conn->prepare("SELECT id, name, price, image FROM products WHERE category_id = ? AND id <> ? ORDER BY id DESC LIMIT 4");
        $stmtRel->bind_param('ii', $catId, $id);
        $stmtRel->execute();
        $resRel = $stmtRel->get_result();
        while ($row = $resRel->fetch_assoc()) { $related[] = $row; }
        $stmtRel->close();
    }
}
?>
<main class="py-4">
    <div class="container">
        <?php if (!$product): ?>
            <div class="alert alert-danger">Product not found.</div>
        <?php else: ?>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
                </ol>
            </nav>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <div class="ratio ratio-1x1 bg-light d-flex align-items-center justify-content-center rounded">
                        <?php $img = $product['image'] ? ($BASE_PATH . '/assets/images/' . $product['image']) : ($BASE_PATH . '/assets/images/placeholder.png'); ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-fluid p-3 object-fit-contain">
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <h1 class="h3 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                    <p class="h4 text-primary fw-bold mb-3"><?= htmlspecialchars(number_format((float)$product['price'], 2)) ?> PKR</p>
                    <div class="mb-4 text-muted" style="white-space: pre-line;">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>

                    <form class="d-flex align-items-center gap-2" id="add-to-cart-form">
                        <label for="quantity" class="form-label mb-0">Qty</label>
                        <div class="input-group" style="width: 160px;">
                            <button class="btn btn-outline-secondary" type="button" id="qty-minus" aria-label="Decrease quantity">-</button>
                            <input type="number" id="quantity" name="quantity" class="form-control text-center" value="1" min="1">
                            <button class="btn btn-outline-secondary" type="button" id="qty-plus" aria-label="Increase quantity">+</button>
                        </div>
                        <a href="add_to_cart.php?product_id=<?= (int)$product['id'] ?>&quantity=1" class="btn btn-primary add-to-cart" id="add-to-cart-btn">
                            <i class="fa fa-cart-plus me-1"></i> Add to Cart
                        </a>
                    </form>

                    <script>
                        // Sync quantity to add-to-cart link
                        (function() {
                            const qtyInput = document.getElementById('quantity');
                            const btn = document.getElementById('add-to-cart-btn');
                            const minus = document.getElementById('qty-minus');
                            const plus = document.getElementById('qty-plus');
                            const baseHref = btn.getAttribute('href').replace(/(&|\?)quantity=\d+/, '');

                            function updateHref() {
                                const qty = Math.max(1, parseInt(qtyInput.value || '1', 10));
                                btn.setAttribute('href', baseHref + (baseHref.includes('?') ? '&' : '?') + 'quantity=' + qty);
                            }
                            qtyInput.addEventListener('input', updateHref);
                            minus.addEventListener('click', function() {
                                qtyInput.value = Math.max(1, (parseInt(qtyInput.value||'1',10)-1));
                                updateHref();
                            });
                            plus.addEventListener('click', function() {
                                qtyInput.value = Math.max(1, (parseInt(qtyInput.value||'1',10)+1));
                                updateHref();
                            });
                            updateHref();
                        })();
                    </script>
                </div>
            </div>

            <?php if (!empty($related)): ?>
                <hr class="my-5">
                <h2 class="h5 mb-3">You may also like</h2>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
                    <?php foreach ($related as $p): ?>
                        <div class="col">
                            <div class="card h-100">
                                <a href="product.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none text-dark">
                                    <div class="ratio ratio-1x1 bg-light">
                                        <?php $rimg = $p['image'] ? ($BASE_PATH . '/assets/images/' . $p['image']) : ($BASE_PATH . '/assets/images/placeholder.png'); ?>
                                        <img src="<?= htmlspecialchars($rimg) ?>" class="card-img-top object-fit-contain p-3" alt="<?= htmlspecialchars($p['name']) ?>">
                                    </div>
                                    <div class="card-body">
                                        <h3 class="h6 card-title text-truncate mb-2" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></h3>
                                        <p class="fw-bold text-primary mb-0"><?= htmlspecialchars(number_format((float)$p['price'], 2)) ?> PKR</p>
                                    </div>
                                </a>
                                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                                    <a href="add_to_cart.php?product_id=<?= (int)$p['id'] ?>&quantity=1" class="btn btn-outline-primary w-100 add-to-cart">
                                        <i class="fa fa-cart-plus me-1"></i> Add to Cart
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
