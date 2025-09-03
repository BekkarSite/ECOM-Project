<!-- public/index.php -->
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/includes/public/public_header.php';

// Fetch categories for highlights (limit 6)
$categories = [];
if ($res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC LIMIT 6")) {
    while ($row = $res->fetch_assoc()) { $categories[] = $row; }
}

// Fetch featured products as configured; fallback to latest
$featured = [];
// try to read selection from settings
$selJson = get_setting($conn, 'featured_product_ids', '[]');
$sel = json_decode((string)$selJson, true);
if (is_array($sel)) {
    // normalize to ints and keep order
    $ids = array_values(array_filter(array_map('intval', $sel), fn($v)=>$v>0));
    if (!empty($ids)) {
        // Limit to 8 for home; preserve order using FIELD()
        $idsLimited = array_slice($ids, 0, 8);
        $placeholders = implode(',', array_fill(0, count($idsLimited), '?'));
        $types = str_repeat('i', count($idsLimited));
        $orderField = implode(',', $idsLimited);
        $sql = "SELECT id, name, price, image FROM products WHERE id IN ($placeholders) ORDER BY FIELD(id, $orderField)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($types, ...$idsLimited);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) { $featured[] = $row; }
            $stmt->close();
        }
    }
}
// Fallback: latest 8
if (empty($featured)) {
    if ($stmt = $conn->prepare("SELECT id, name, price, image FROM products ORDER BY id DESC LIMIT 8")) {
        $stmt->execute();
        $r = $stmt->get_result();
        while ($row = $r->fetch_assoc()) { $featured[] = $row; }
        $stmt->close();
    }
}
?>

<style>
/********************
  HOME PAGE STYLES
*********************/
.hero {
  background: radial-gradient(1000px 500px at 20% 20%, #e9f5ff 0%, transparent 60%),
              radial-gradient(800px 400px at 80% 0%, #fff5e6 0%, transparent 60%),
              linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
}
.hero-cta .btn { padding-left: 1.25rem; padding-right: 1.25rem; }
.category-pill {
  border-radius: 12px;
  border: 1px solid #e9ecef;
  transition: transform .15s ease, box-shadow .15s ease;
}
.category-pill:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.07); }
.product-card .card-img-top { object-fit: contain; }
</style>

<main>
    <!-- Hero -->
    <section class="hero py-5 py-md-6 border-bottom">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-12 col-lg-7">
                    <h1 class="display-5 fw-bold mb-3">Discover quality products at great prices</h1>
                    <p class="lead text-muted mb-4">Shop the latest arrivals, bestsellers, and hand-picked deals. Fast checkout. Secure payments.</p>
                    <div class="hero-cta d-flex flex-wrap gap-2">
                        <a href="products.php" class="btn btn-primary btn-lg"><i class="fa fa-store me-2"></i> Shop Now</a>
                        <a href="categories.php" class="btn btn-outline-secondary btn-lg"><i class="fa fa-list me-2"></i> Browse Categories</a>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="ratio ratio-1x1 bg-light rounded d-flex align-items-center justify-content-center">
                        <div class="text-center p-4">
                            <i class="fa fa-cart-shopping text-primary" style="font-size:72px"></i>
                            <div class="mt-3 text-muted">Your one-stop shop for everything.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories highlight -->
    <section class="py-5 border-bottom">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">Shop by Category</h2>
                <a class="text-decoration-none" href="categories.php">View all</a>
            </div>
            <?php if (!empty($categories)): ?>
                <div class="row g-3">
                    <?php foreach ($categories as $cat): ?>
                        <div class="col-6 col-md-4 col-lg-2">
                            <a href="products.php?category_id=<?= (int)$cat['id']; ?>" class="text-decoration-none text-dark">
                                <div class="category-pill p-3 h-100 d-flex align-items-center justify-content-center bg-white">
                                    <span class="fw-semibold text-center text-truncate" title="<?= htmlspecialchars($cat['name']); ?>"><?= htmlspecialchars($cat['name']); ?></span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light mb-0">No categories available yet.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Featured products -->
    <section class="py-5">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h4 mb-0">Featured Products</h2>
                <a class="text-decoration-none" href="products.php">Shop all</a>
            </div>
            <?php if (!empty($featured)): ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach ($featured as $p): ?>
                        <div class="col">
                            <div class="card product-card h-100">
                                <a href="product.php?id=<?= (int)$p['id']; ?>" class="text-decoration-none text-dark">
                                    <div class="ratio ratio-1x1 bg-light">
                                        <?php $img = $p['image'] ? ($BASE_PATH . '/assets/images/' . $p['image']) : ($BASE_PATH . '/assets/images/placeholder.svg'); ?>
                                        <img src="<?= htmlspecialchars($img); ?>" class="card-img-top p-3" alt="<?= htmlspecialchars($p['name']); ?>">
                                    </div>
                                    <div class="card-body">
                                        <h3 class="h6 card-title text-truncate mb-2" title="<?= htmlspecialchars($p['name']); ?>"><?= htmlspecialchars($p['name']); ?></h3>
                                        <div class="fw-bold text-primary mb-0"><?= htmlspecialchars(number_format((float)$p['price'], 2)); ?> PKR</div>
                                    </div>
                                </a>
                                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                                    <a href="add-to-cart?product_id=<?= (int)$p['id']; ?>&quantity=1" class="btn btn-outline-primary w-100 add-to-cart">
                                        <i class="fa fa-cart-plus me-1"></i> Add to Cart
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light mb-0">No products available.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Newsletter CTA -->
    <section class="py-5 bg-light border-top border-bottom">
        <div class="container">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-6">
                    <h2 class="h4 mb-1">Stay in the loop</h2>
                    <p class="text-muted mb-0">Get updates on new arrivals, promotions, and tips.</p>
                </div>
                <div class="col-12 col-md-6">
                    <form action="subscribe.php" method="POST" class="d-flex gap-2">
                        <label for="newsletter-home" class="visually-hidden">Email address</label>
                        <input type="email" id="newsletter-home" name="email" class="form-control" placeholder="Enter your email" required>
                        <button class="btn btn-warning" type="submit"><i class="fa fa-envelope me-1"></i> Subscribe</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
