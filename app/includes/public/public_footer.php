<?php
// Ensure $BASE_PATH is available if footer is included standalone
if (!isset($BASE_PATH)) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $projRoot = str_replace('\\', '/', realpath(__DIR__ . '/../../../'));
    $baseUri = '';
    if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
        $baseUri = rtrim(substr($projRoot, strlen($docRoot)), '/');
    }
    $BASE_PATH = $baseUri ? '/' . ltrim($baseUri, '/') : '';
}
?><!-- public_footer.php -->
<footer class="bg-dark text-white mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <?php
                require_once __DIR__ . '/../../helpers/settings.php';
                require_once __DIR__ . '/../../../config/db.php';
                $footerLogoPath = get_setting($conn, 'site_logo', 'assets/images/logo.png');
                $companyPhone = get_setting($conn, 'company_phone', '+1 234 567 890');
                $companyAddress = get_setting($conn, 'company_address', '123 Main Street, City, Country');
                ?>
                <a href="index.php" class="d-inline-block mb-3">
                    <img src="<?= $BASE_PATH ?>/<?php echo htmlspecialchars($footerLogoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="img-fluid" style="max-height:48px">
                </a>
                <p class="mb-1"><i class="fa fa-phone me-2"></i> <?php echo htmlspecialchars($companyPhone, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="mb-0"><i class="fa fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="col-6 col-md-2">
                <h5 class="text-warning">Company</h5>
                <ul class="list-unstyled">
                    <li><a class="text-white text-decoration-none" href="about.php">About Us</a></li>
                    <li><a class="text-white text-decoration-none" href="contact.php">Contact</a></li>
                    <li><a class="text-white text-decoration-none" href="privacy.php">Privacy Policy</a></li>
                    <li><a class="text-white text-decoration-none" href="terms.php">Terms of Service</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3">
                <h5 class="text-warning">Follow Us</h5>
                <ul class="list-unstyled">
                    <?php
                    $fb = get_setting($conn, 'social_facebook', '#');
                    $tw = get_setting($conn, 'social_twitter', '#');
                    $ig = get_setting($conn, 'social_instagram', '#');
                    ?>
                    <li><a class="text-white text-decoration-none" href="<?php echo htmlspecialchars($fb, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-facebook me-2"></i>Facebook</a></li>
                    <li><a class="text-white text-decoration-none" href="<?php echo htmlspecialchars($tw, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-twitter me-2"></i>Twitter</a></li>
                    <li><a class="text-white text-decoration-none" href="<?php echo htmlspecialchars($ig, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-instagram me-2"></i>Instagram</a></li>
                </ul>
            </div>
            <div class="col-12 col-md-3">
                <h5 class="text-warning">Newsletter</h5>
                <form action="subscribe.php" method="POST">
                    <label for="newsletter-email" class="visually-hidden">Email</label>
                    <div class="input-group">
                        <input type="email" id="newsletter-email" name="email" class="form-control" placeholder="Enter your email" required>
                        <button class="btn btn-warning" type="submit">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row mt-4 pt-3 border-top border-secondary align-items-center">
            <div class="col-12 col-md-6 mb-3 mb-md-0">
                <?php $companyName = get_setting($conn, 'company_name', 'My eCommerce Store'); ?>
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-md-end align-items-center">
                <div class="me-3 d-none d-md-block">
                    <span class="me-2">We Accept</span>
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><img src="<?= $BASE_PATH ?>/assets/images/payment/paypal.png" alt="PayPal" class="payment-icon"></li>
                        <li class="list-inline-item"><img src="<?= $BASE_PATH ?>/assets/images/payment/visa.png" alt="Visa" class="payment-icon"></li>
                        <li class="list-inline-item"><img src="<?= $BASE_PATH ?>/assets/images/payment/mastercard.png" alt="MasterCard" class="payment-icon"></li>
                </ul>
                </div>
                <a href="#top" class="btn btn-outline-light btn-sm"><i class="fa fa-arrow-up me-1"></i> Back to top</a>
            </div>
        </div>
    </div>
</footer>
<script src="<?= $BASE_PATH ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= $BASE_PATH ?>/assets/js/custom/add_to_cart.js"></script>
<script src="<?= $BASE_PATH ?>/assets/js/custom/cart.js"></script>
</body>
</html>
