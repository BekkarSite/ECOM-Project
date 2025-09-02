<footer class="bg-dark text-white mt-5 admin-footer">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> My eCommerce Store · Admin</p>
            <small class="text-secondary">Powered by Bootstrap</small>
        </div>
    </div>
</footer>

<script>
// Hide the loader once the page has fully loaded
window.addEventListener('load', function() {
    var loader = document.getElementById('loader');
    if (loader) loader.classList.add('hidden');
});
</script>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
