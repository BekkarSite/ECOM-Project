<footer class="admin-footer">
  <div class="container-fluid px-3 px-lg-4 py-3">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
      <p class="mb-0">&copy; <?php echo date('Y'); ?> My eCommerce Store · Admin</p>
      <small class="text-secondary">UI powered by Bootstrap</small>
    </div>
  </div>
</footer>

<script>
// Hide the loader once the page has fully loaded
window.addEventListener('load', function () {
  var loader = document.getElementById('loader');
  if (loader) loader.classList.add('hidden');
});

// Sidebar toggle handled in header script
</script>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
