<footer class="admin-footer mt-5">
  <div class="container py-3">
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

// Sidebar toggle for mobile
(function(){
  var toggle = document.getElementById('sidebarToggle');
  if (!toggle) return;
  toggle.addEventListener('click', function(){
    if (document.body.classList.contains('sidebar-open')) {
      document.body.classList.remove('sidebar-open');
    } else {
      document.body.classList.add('sidebar-open');
    }
  });
})();
</script>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
