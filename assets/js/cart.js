async function addToCart(plantId, quantity = 1) {
    try {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('plant_id', plantId);
        formData.append('quantity', quantity);

        const res = await fetch(`${APP_URL}/api/cart.php`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            updateCartBadge(data.total_items);
            showToast(`Added to cart! Total items: ${data.total_items}`, 'success');
        } else {
            showToast(data.message || 'Could not add to cart', 'error');
        }
    } catch (err) {
        console.error('Cart Error:', err);
        showToast('Network error while updating cart.', 'error');
    }
}

async function updateCartQuantity(plantId, quantity) {
    try {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('plant_id', plantId);
        formData.append('quantity', quantity);

        const res = await fetch(`${APP_URL}/api/cart.php`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            window.location.reload();
        }
    } catch (err) {
        console.error('Cart Update Error:', err);
    }
}

async function removeFromCart(plantId) {
    try {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('plant_id', plantId);

        const res = await fetch(`${APP_URL}/api/cart.php`, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.status === 'success') {
            window.location.reload();
        }
    } catch (err) {
        console.error('Cart Remove Error:', err);
    }
}

function updateCartBadge(count) {
    const badge = document.getElementById('cartBadgeCount');
    if (badge) {
        badge.textContent = count;
    }
}
