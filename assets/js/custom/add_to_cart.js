// assets/js/add_to_cart.js
// Handles AJAX add-to-cart actions and updates cart count

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('a.add-to-cart').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      const url = link.getAttribute('href');

      fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(async response => {
          if (response.status === 401) {
            const base = window.BASE_PATH || '';
            const next = encodeURIComponent(window.location.pathname + window.location.search);
            window.location.href = `${base}/login.php?next=${next}`;
            return null;
          }
          // Try to parse JSON; if it fails, fallback to navigating to the URL
          try {
            return await response.json();
          } catch (_) {
            window.location.href = url;
            return null;
          }
        })
        .then(data => {
          if (!data) return;
          if (data && data.error) {
            alert(data.error);
            return;
          }
          if (typeof data.count !== 'undefined') {
            const countEl = document.querySelector('.cart-count');
            if (countEl) {
              countEl.textContent = data.count;
            }
          }
        })
        .catch(error => {
          console.error('Add to cart failed', error);
          // As a fallback, let the browser handle the link normally
          window.location.href = url;
        });
    });
  });
});
