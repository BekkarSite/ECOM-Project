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
                    $socials = [
                        ['key' => 'facebook',  'icon' => 'fa-brands fa-facebook',  'label' => 'Facebook'],
                        ['key' => 'twitter',   'icon' => 'fa-brands fa-twitter',   'label' => 'Twitter'],
                        ['key' => 'instagram', 'icon' => 'fa-brands fa-instagram', 'label' => 'Instagram'],
                        ['key' => 'youtube',   'icon' => 'fa-brands fa-youtube',   'label' => 'YouTube'],
                        ['key' => 'linkedin',  'icon' => 'fa-brands fa-linkedin',  'label' => 'LinkedIn'],
                        ['key' => 'tiktok',    'icon' => 'fa-brands fa-tiktok',    'label' => 'TikTok'],
                        ['key' => 'telegram',  'icon' => 'fa-brands fa-telegram',  'label' => 'Telegram'],
                        ['key' => 'pinterest', 'icon' => 'fa-brands fa-pinterest', 'label' => 'Pinterest'],
                    ];
                    foreach ($socials as $s) {
                        $url = trim((string)get_setting($conn, 'social_' . $s['key'], ''));
                        $enabled = get_setting($conn, 'social_' . $s['key'] . '_enabled', '1');
                        if ($enabled === '1' && $url !== '') {
                            $h = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                            $label = htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8');
                            $icon = $s['icon'];
                            echo '<li><a class="text-white text-decoration-none" href="' . $h . '" target="_blank" rel="noopener noreferrer"><i class="' . $icon . ' me-2"></i>' . $label . '</a></li>';
                        }
                    }
                    ?>
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
            <div class="col-12 col-md-6 mb-3 mb-md-0 d-flex align-items-center gap-3">
                <?php $companyName = get_setting($conn, 'company_name', 'My eCommerce Store'); ?>
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                <?php
                  $waEnabled = get_setting($conn, 'whatsapp_enabled', '0');
                  $waNumber  = trim((string)get_setting($conn, 'whatsapp_number', ''));
                  if ($waEnabled === '1' && $waNumber !== ''):
                    $waLink = 'https://wa.me/' . preg_replace('/\D+/', '', $waNumber);
                ?>
                  <a href="<?php echo htmlspecialchars($waLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                  </a>
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-md-end align-items-center">
                <div class="me-3 d-none d-md-block">
                    <span class="me-2">We accept</span>
                    <div class="d-inline-flex align-items-center gap-3 fs-4 text-secondary">
                        <i class="fab fa-cc-visa" aria-label="Visa" title="Visa"></i>
                        <i class="fab fa-cc-mastercard" aria-label="Mastercard" title="Mastercard"></i>
                        <i class="fab fa-cc-amex" aria-label="American Express" title="American Express"></i>
                        <i class="fab fa-cc-discover" aria-label="Discover" title="Discover"></i>
                        <i class="fab fa-cc-paypal" aria-label="PayPal" title="PayPal"></i>
                    </div>
                </div>
                <a href="#top" class="btn btn-outline-light btn-sm"><i class="fa fa-arrow-up me-1"></i> Back to top</a>
            </div>
        </div>
    </div>
</footer>
<script>window.BASE_PATH = '<?= $BASE_PATH ?>';</script>
<script src="<?= $BASE_PATH ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= $BASE_PATH ?>/assets/js/custom/add_to_cart.js"></script>
<script src="<?= $BASE_PATH ?>/assets/js/custom/cart.js"></script>
<script src="<?= $BASE_PATH ?>/assets/js/custom/search_suggest.js"></script>
</body>
</html>
