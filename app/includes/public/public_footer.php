<!-- footer.php -->
<footer class="footer">
    <div class="footer-container">
        <!-- Logo Section with Phone and Address -->
        <div class="footer-logo">
            <br>
            <a href="index.php" aria-label="Go to homepage">
                <img src="../assets/images/logo.png" alt="My eCommerce Logo" />
            </a>
            <br><br><br>
            <div class="contact-info">
                <p class="phone-number"><i class="fa fa-phone"></i> +1 234 567 890</p>
                <p class="address"><i class="fa fa-map-marker-alt"></i> 123 Main Street, City, Country</p>
            </div>
        </div>

        <!-- Quick Links Section -->
        <div class="footer-links">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="about.php" aria-label="Learn more about us">About Us</a></li>
                <li><a href="contact.php" aria-label="Contact us">Contact</a></li>
                <li><a href="privacy.php" aria-label="Privacy Policy">Privacy Policy</a></li>
                <li><a href="terms.php" aria-label="Terms and Conditions">Terms of Service</a></li>
            </ul>
        </div>

        <!-- Social Media Section -->
        <div class="footer-social">
            <h4>Follow Us</h4>
            <ul>
                <li><a href="#" aria-label="Follow us on Facebook"><i class="fa fa-facebook"></i> Facebook</a></li>
                <li><a href="#" aria-label="Follow us on Twitter"><i class="fa fa-twitter"></i> Twitter</a></li>
                <li><a href="#" aria-label="Follow us on Instagram"><i class="fa fa-instagram"></i> Instagram</a></li>
            </ul>
        </div>

        <!-- Newsletter Section -->
        <div class="footer-newsletter">
            <h4>Subscribe to Our Newsletter</h4>
            <form action="subscribe.php" method="POST" aria-labelledby="newsletter-form">
                <label for="newsletter-email" class="visually-hidden">Enter your email to subscribe</label>
                <input type="email" id="newsletter-email" name="email" placeholder="Enter your email" required />
                <button type="submit">Subscribe</button>
            </form>
        </div>
    </div>

    <!-- Copyright Section -->
    <div class="footer-bottom">
        <p>&copy; 2025 My eCommerce Store. All rights reserved.</p>
    </div>

    <!-- Additional Footer Information Section -->
    <div class="footer-additional-info">
        <div class="payment-methods">
            <h5>We Accept</h5>
            <ul>
                <li><img src="../assets/images/payment/paypal.png" alt="PayPal"></li>
                <li><img src="../assets/images/payment/visa.png" alt="Visa"></li>
                <li><img src="../assets/images/payment/mastercard.png" alt="MasterCard"></li>
            </ul>
        </div>
        <div class="site-credits">
            <p>Site by <a href="https://example.com" target="_blank">Example Web Design</a></p>
        </div>
    </div>
</footer>
<script src="../assets/js/bootstrap.bundle.min.js"></script>

</body>

</html>