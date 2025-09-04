<?php
// public/404.php
session_start();
http_response_code(404);
require_once __DIR__ . '/../app/includes/public/public_header.php';
?>

<style>
/* 404 Page Styles */
.error-hero {
  background: radial-gradient(900px 400px at 10% 10%, #f2f7ff 0%, transparent 60%),
              radial-gradient(700px 300px at 90% 0%, #fff4e5 0%, transparent 60%),
              linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
}
.error-code {
  font-size: clamp(72px, 12vw, 140px);
  line-height: 1;
  font-weight: 800;
  letter-spacing: -2px;
}
.error-actions .btn { padding-left: 1.25rem; padding-right: 1.25rem; }
</style>

<main>
  <section class="error-hero py-5 py-md-6 border-bottom">
    <div class="container">
      <div class="row align-items-center g-4">
        <div class="col-12 col-lg-7">
          <div class="text-uppercase text-muted fw-semibold mb-2">Error</div>
          <div class="error-code text-primary">404</div>
          <h1 class="display-6 fw-bold mb-3">Page not found</h1>
          <p class="lead text-muted mb-4">
            The page you are looking for may have been moved, deleted, or never existed.
          </p>
          <div class="error-actions d-flex flex-wrap gap-2">
            <a href="<?= $BASE_PATH ?>/" class="btn btn-primary btn-lg"><i class="fa fa-house me-2"></i>Back to Home</a>
            <a href="<?= $BASE_PATH ?>/products.php" class="btn btn-outline-secondary btn-lg"><i class="fa fa-store me-2"></i>Browse Products</a>
          </div>
        </div>
        <div class="col-12 col-lg-5">
          <div class="bg-light rounded p-4">
            <form action="<?= $BASE_PATH ?>/products.php" method="GET" class="d-flex gap-2" role="search">
              <label for="search-404" class="visually-hidden">Search products</label>
              <input type="text" id="search-404" name="query" class="form-control" placeholder="Search products..." required>
              <button class="btn btn-outline-primary" type="submit"><i class="fa fa-search"></i></button>
            </form>
            <div class="text-muted small mt-3">
              Tip: Try searching for a product name or browse categories.
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="py-5">
    <div class="container">
      <div class="row g-3">
        <div class="col-12">
          <h2 class="h5 mb-3">Quick links</h2>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-dark" href="<?= $BASE_PATH ?>/categories.php"><i class="fa fa-tags me-2"></i>Categories</a>
            <a class="btn btn-outline-dark" href="<?= $BASE_PATH ?>/cart.php"><i class="fa fa-shopping-cart me-2"></i>Cart</a>
            <a class="btn btn-outline-dark" href="<?= $BASE_PATH ?>/contact.php"><i class="fa fa-envelope me-2"></i>Contact</a>
            <a class="btn btn-outline-dark" href="<?= $BASE_PATH ?>/about.php"><i class="fa fa-circle-info me-2"></i>About</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../app/includes/public/public_footer.php'; ?>
