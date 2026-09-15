// Client-side shopping basket operations and reactive cart badge updates.

document.addEventListener('DOMContentLoaded', () => {
    // Initialize cart table controls.
    initCartControls();
});

// Update navbar cart badge counter across all header badge elements.
function updateCartBadge(count) {
    const total = parseInt(count, 10) || 0;
    const badge = document.getElementById('cartBadgeCount');
    if (badge) {
        badge.textContent = total;
    }
    const badgeAlt = document.getElementById('cart-count');
    if (badgeAlt) {
        badgeAlt.textContent = total;
    }
}

// Add plant item to cart via asynchronous request.
async function addToCart(plantId, quantity = 1) {
    try {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('plant_id', plantId);
        formData.append('quantity', quantity);

        const targetUrl = (typeof APP_URL !== 'undefined' ? APP_URL : window.location.origin + '/ecosprout') + '/api/cart.php';
        const res = await fetch(targetUrl, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            updateCartBadge(data.total_items);
            if (typeof showToast === 'function') {
                showToast(data.message, 'success');
            } else {
                alert(data.message);
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Could not add plant to basket.', 'error');
            } else {
                alert(data.message || 'Could not add plant to basket.');
            }
        }
    } catch (err) {
        console.error('Cart addition error:', err);
        if (typeof showToast === 'function') {
            showToast('Network error updating cart.', 'error');
        }
    }
}

// Update quantity for an existing cart item.
async function updateCartQuantity(plantId, quantity) {
    try {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('plant_id', plantId);
        formData.append('quantity', quantity);

        const targetUrl = (typeof APP_URL !== 'undefined' ? APP_URL : window.location.origin + '/ecosprout') + '/api/cart.php';
        const res = await fetch(targetUrl, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            updateCartBadge(data.total_items);
            if (parseInt(quantity, 10) <= 0) {
                removeCartRowFromDOM(plantId);
            } else {
                const subSpan = document.getElementById('subtotal-' + plantId);
                if (subSpan && data.item_subtotal) {
                    subSpan.textContent = 'LKR ' + parseFloat(data.item_subtotal).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    subSpan.setAttribute('data-subtotal', data.item_subtotal);
                }
                recalculateCartSummary();
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Error updating quantity.', 'error');
            }
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (err) {
        console.error('Cart quantity update error:', err);
    }
}

// Remove item completely from cart.
async function removeFromCart(plantId) {
    if (!confirm('Remove this plant from your shopping basket?')) {
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('plant_id', plantId);

        const targetUrl = (typeof APP_URL !== 'undefined' ? APP_URL : window.location.origin + '/ecosprout') + '/api/cart.php';
        const res = await fetch(targetUrl, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            updateCartBadge(data.total_items);
            removeCartRowFromDOM(plantId);
            if (typeof showToast === 'function') {
                showToast('Item removed from basket.', 'success');
            }
        } else {
            if (typeof showToast === 'function') {
                showToast(data.message || 'Could not remove item.', 'error');
            }
        }
    } catch (err) {
        console.error('Cart removal error:', err);
    }
}

// Clear all items from the shopping cart.
async function clearEntireCart() {
    if (!confirm('Empty all items from your basket?')) {
        return;
    }
    try {
        const formData = new FormData();
        formData.append('action', 'clear');

        const targetUrl = (typeof APP_URL !== 'undefined' ? APP_URL : window.location.origin + '/ecosprout') + '/api/cart.php';
        const res = await fetch(targetUrl, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            updateCartBadge(0);
            window.location.reload();
        }
    } catch (err) {
        console.error('Clear cart error:', err);
    }
}

// Remove DOM table row and toggle empty view when table is empty.
function removeCartRowFromDOM(plantId) {
    const row = document.getElementById('cart-row-' + plantId);
    if (row) {
        row.remove();
    }
    const remainingRows = document.querySelectorAll('#cartTable tbody tr');
    if (!remainingRows || remainingRows.length === 0) {
        window.location.reload();
    } else {
        recalculateCartSummary();
    }
}

// Recalculate summary total on cart view.
function recalculateCartSummary() {
    let total = 0.0;
    document.querySelectorAll('.item-subtotal-val').forEach(el => {
        const val = parseFloat(el.getAttribute('data-subtotal')) || 0;
        total += val;
    });
    const subtotalDisplay = document.getElementById('cartSubtotalAmount');
    const totalDisplay = document.getElementById('cartGrandTotalAmount');
    const formatted = 'LKR ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (subtotalDisplay) subtotalDisplay.textContent = formatted;
    if (totalDisplay) totalDisplay.textContent = formatted;
}

// Bind quantity increment, decrement, and direct input handlers.
function initCartControls() {
    // Quantity increment button.
    document.querySelectorAll('.btn-cart-inc').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const max = parseInt(this.getAttribute('data-max'), 10) || 9999;
            const input = document.getElementById('qty-input-' + id);
            if (input) {
                let current = parseInt(input.value, 10) || 1;
                if (current < max) {
                    current += 1;
                    input.value = current;
                    updateCartQuantity(id, current);
                } else if (typeof showToast === 'function') {
                    showToast('Maximum available stock reached (' + max + ').', 'error');
                }
            }
        });
    });

    // Quantity decrement button.
    document.querySelectorAll('.btn-cart-dec').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const input = document.getElementById('qty-input-' + id);
            if (input) {
                let current = parseInt(input.value, 10) || 1;
                if (current > 1) {
                    current -= 1;
                    input.value = current;
                    updateCartQuantity(id, current);
                } else {
                    removeFromCart(id);
                }
            }
        });
    });

    // Quantity input direct change.
    document.querySelectorAll('.cart-qty-field').forEach(input => {
        input.addEventListener('change', function () {
            const id = this.getAttribute('data-id');
            const max = parseInt(this.getAttribute('max'), 10) || 9999;
            let val = parseInt(this.value, 10);
            if (isNaN(val) || val < 1) {
                val = 1;
            } else if (val > max) {
                val = max;
                if (typeof showToast === 'function') {
                    showToast('Adjusted to maximum available stock (' + max + ').', 'error');
                }
            }
            this.value = val;
            updateCartQuantity(id, val);
        });
    });
}