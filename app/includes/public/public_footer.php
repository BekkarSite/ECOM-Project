<!-- footer.php -->
<footer class="bg-dark text-white mt-5">
    <div class="container py-5">
        <div class="row">
            <div class="col-md-3 mb-4">
                <a href="index.php" aria-label="Go to homepage" class="d-inline-block mb-3">
                    <img src="../assets/images/logo.png" alt="My eCommerce Logo" class="img-fluid">
                </a>
                <p><i class="fa fa-phone me-2"></i> +1 234 567 890</p>
                <p><i class="fa fa-map-marker-alt me-2"></i> 123 Main Street, City, Country</p>
            </div>
            <div class="col-md-3 mb-4">
                <h5 class="text-warning">Quick Links</h5>
                <ul class="list-unstyled">
                    <li><a class="text-white" href="about.php" aria-label="Learn more about us">About Us</a></li>
                    <li><a class="text-white" href="contact.php" aria-label="Contact us">Contact</a></li>
                    <li><a class="text-white" href="privacy.php" aria-label="Privacy Policy">Privacy Policy</a></li>
                    <li><a class="text-white" href="terms.php" aria-label="Terms and Conditions">Terms of Service</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5 class="text-warning">Follow Us</h5>
                <ul class="list-unstyled">
                    <li><a class="text-white" href="#" aria-label="Follow us on Facebook"><i class="fa fa-facebook me-2"></i>Facebook</a></li>
                    <li><a class="text-white" href="#" aria-label="Follow us on Twitter"><i class="fa fa-twitter me-2"></i>Twitter</a></li>
                    <li><a class="text-white" href="#" aria-label="Follow us on Instagram"><i class="fa fa-instagram me-2"></i>Instagram</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h5 class="text-warning">Subscribe to Our Newsletter</h5>
                <form action="subscribe.php" method="POST">
                    <label for="newsletter-email" class="visually-hidden">Enter your email to subscribe</label>
                    <div class="input-group">
                        <input type="email" id="newsletter-email" name="email" class="form-control" placeholder="Enter your email" required>
                        <button class="btn btn-warning" type="submit">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row mt-4 pt-3 border-top border-secondary">
            <div class="col-md-6 mb-3 mb-md-0">
                <p class="mb-0">&copy; 2025 My eCommerce Store. All rights reserved.</p>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-column flex-md-row justify-content-md-end align-items-md-center">
                    <div class="me-md-3 mb-2 mb-md-0">
                        <span class="me-2">We Accept</span>
                        <ul class="list-inline mb-0">
                            <li class="list-inline-item"><img src="../assets/images/payment/paypal.png" alt="PayPal" class="payment-icon"></li>
                            <li class="list-inline-item"><img src="../assets/images/payment/visa.png" alt="Visa" class="payment-icon"></li>
                            <li class="list-inline-item"><img src="../assets/images/payment/mastercard.png" alt="MasterCard" class="payment-icon"></li>
                        </ul>
                    </div>
                    <div class="text-md-end">
                        <small>Site by <a href="https://example.com" target="_blank" class="text-warning text-decoration-none">Example Web Design</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/custom/add_to_cart.js"></script>

</body>

</html>