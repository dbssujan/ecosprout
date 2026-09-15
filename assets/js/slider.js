/**
 * EcoSprout - Top Picks Auto Slider
 * assets/js/slider.js
 */

document.addEventListener('DOMContentLoaded', () => {
    const viewport = document.getElementById('plantSliderViewport');
    const prevBtn = document.getElementById('sliderPrevBtn');
    const nextBtn = document.getElementById('sliderNextBtn');
    if (!viewport) return;

    // 280px card width + 24px gap = 304px scroll step
    const scrollStep = 304;
    let autoSlideTimer = null;

    function slideNext() {
        const maxScroll = viewport.scrollWidth - viewport.clientWidth;
        if (viewport.scrollLeft >= maxScroll - 15) {
            viewport.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
            viewport.scrollBy({ left: scrollStep, behavior: 'smooth' });
        }
    }

    function slidePrev() {
        if (viewport.scrollLeft <= 15) {
            viewport.scrollTo({ left: viewport.scrollWidth, behavior: 'smooth' });
        } else {
            viewport.scrollBy({ left: -scrollStep, behavior: 'smooth' });
        }
    }

    function startAutoSlide() {
        stopAutoSlide();
        autoSlideTimer = setInterval(slideNext, 3500);
    }

    function stopAutoSlide() {
        if (autoSlideTimer) {
            clearInterval(autoSlideTimer);
            autoSlideTimer = null;
        }
    }

    // Manual navigation buttons
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            slideNext();
            startAutoSlide();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            slidePrev();
            startAutoSlide();
        });
    }

    // Pause auto-sliding on user hover or touch interaction
    viewport.addEventListener('mouseenter', stopAutoSlide);
    viewport.addEventListener('mouseleave', startAutoSlide);
    viewport.addEventListener('touchstart', stopAutoSlide, { passive: true });
    viewport.addEventListener('touchend', startAutoSlide, { passive: true });

    // Start playback
    startAutoSlide();
});

// Resilient cart fallback in case cart.js is not loaded on the homepage
if (typeof window.addToCart !== 'function') {
    window.addToCart = async function(plantId, quantity = 1) {
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
                const badge = document.getElementById('cartBadgeCount');
                if (badge) badge.textContent = data.total_items;
                if (typeof showToast === 'function') {
                    showToast(`Added to cart! Total items: ${data.total_items}`, 'success');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Could not add to cart', 'error');
                }
            }
        } catch (err) {
            console.error('Cart Error:', err);
        }
    };
}