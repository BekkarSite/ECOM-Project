// assets/js/add_to_cart.js
// Handles AJAX add-to-cart actions and updates cart count

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a.add-to-cart').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            const url = link.getAttribute('href');
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data && typeof data.count !== 'undefined') {
                        const countEl = document.querySelector('.cart-count');
                        if (countEl) {
                            countEl.textContent = data.count;
                        }
                    }
                })
                .catch(error => {
                    console.error('Add to cart failed', error);
                });
        });
    });
});