// Secure payment gateway handler and client validation for checkout pipeline.

document.addEventListener('DOMContentLoaded', () => {
    // Initialize checkout payment method switcher and card gateway formatting.
    initCheckoutGateway();
});

function initCheckoutGateway() {
    const cardRadio = document.getElementById('pm_card');
    const codRadio = document.getElementById('pm_cod');
    const cardPanel = document.getElementById('cardGatewayPanel');
    const cardNumberInput = document.getElementById('card_number');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCvvInput = document.getElementById('card_cvv');
    const cardBrandBadge = document.getElementById('cardBrandBadge');
    const checkoutForm = document.getElementById('checkoutForm');
    const submitBtn = document.getElementById('submitOrderBtn');

    if (!checkoutForm) return;

    // Toggle secure card gateway panel visibility.
    function togglePaymentPanel() {
        if (cardRadio && cardRadio.checked) {
            if (cardPanel) cardPanel.style.display = 'block';
        } else {
            if (cardPanel) cardPanel.style.display = 'none';
        }
    }

    if (cardRadio) cardRadio.addEventListener('change', togglePaymentPanel);
    if (codRadio) codRadio.addEventListener('change', togglePaymentPanel);

    // Format credit card number with space grouping and detect brand.
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '').substring(0, 16);
            let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
            this.value = formatted;

            // Brand detection.
            if (cardBrandBadge) {
                if (/^4/.test(val)) {
                    cardBrandBadge.textContent = 'VISA';
                    cardBrandBadge.style.color = '#1d4ed8';
                } else if (/^(5[1-5]|2[2-7])/.test(val)) {
                    cardBrandBadge.textContent = 'MASTERCARD';
                    cardBrandBadge.style.color = '#ea580c';
                } else if (/^3[47]/.test(val)) {
                    cardBrandBadge.textContent = 'AMEX';
                    cardBrandBadge.style.color = '#0284c7';
                } else {
                    cardBrandBadge.textContent = 'CARD';
                    cardBrandBadge.style.color = '#6b7280';
                }
            }
        });
    }

    // Format expiration date with slash (MM/YY).
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '').substring(0, 4);
            if (val.length >= 2) {
                this.value = val.substring(0, 2) + '/' + val.substring(2);
            } else {
                this.value = val;
            }
        });
    }

    // Restrict CVV input to digits only.
    if (cardCvvInput) {
        cardCvvInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 4);
        });
    }

    // Client-side validation prior to form submission.
    checkoutForm.addEventListener('submit', function (e) {
        const phone = document.getElementById('contact_phone')?.value.trim() || '';
        const address = document.getElementById('shipping_address')?.value.trim() || '';

        if (!phone || !address) {
            alert('Please provide contact phone number and delivery address.');
            e.preventDefault();
            return;
        }

        if (cardRadio && cardRadio.checked) {
            const holder = document.getElementById('card_holder')?.value.trim() || '';
            const num = (cardNumberInput?.value || '').replace(/\s+/g, '');
            const exp = cardExpiryInput?.value.trim() || '';
            const cvv = cardCvvInput?.value.trim() || '';

            if (!holder || holder.length < 3) {
                alert('Please provide the cardholder full name.');
                e.preventDefault();
                return;
            }
            if (num.length < 15) {
                alert('Please enter a valid 15 or 16 digit card number.');
                e.preventDefault();
                return;
            }
            if (!/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(exp)) {
                alert('Please enter a valid expiration date in MM/YY format.');
                e.preventDefault();
                return;
            }
            if (cvv.length < 3) {
                alert('Please enter your 3 or 4 digit CVV security code.');
                e.preventDefault();
                return;
            }
        }

        // Prevent double submission and indicate processing.
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing Transaction...';
        }
    });
}