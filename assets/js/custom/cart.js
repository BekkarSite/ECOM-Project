// assets/js/custom/cart.js
// Handles AJAX updates for cart quantity controls

document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.cart-table');
    if (!table) return;

    table.addEventListener('click', event => {
        // Support clicks on nested elements within the quantity button
        const button = event.target.closest('.qty-btn');
        if (!button) return;

        event.preventDefault();
        const url = button.getAttribute('href');

        fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (!data) return;

                const row = button.closest('tr');

                if (data.removed) {
                    row.remove();
                    if (!table.querySelector('tbody tr')) {
                        table.remove();
                        const msg = document.createElement('p');
                        msg.textContent = 'Your cart is empty.';
                        const container = document.querySelector('main.cart');
                        if (container) container.appendChild(msg);
                    }
                } else {
                    const qtyEl = row.querySelector('.quantity-controls .qty');
                    if (qtyEl) {
                        qtyEl.textContent = data.quantity;
                    }
                    const subtotalEl = row.querySelector('.subtotal');
                    if (subtotalEl) {
                        subtotalEl.textContent = data.subtotal.toFixed(2) + ' PKR';
                    }
                }

                const totalEl = document.getElementById('cart-total');
                if (totalEl) {
                    totalEl.textContent = data.total.toFixed(2) + ' PKR';
                }

                const countEl = document.querySelector('.cart-count');
                if (countEl && typeof data.count !== 'undefined') {
                    countEl.textContent = data.count;
                }
            })
            .catch(err => {
                console.error('Cart update failed', err);
            });
    });
});
