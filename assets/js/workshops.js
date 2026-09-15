function initiateWorkshopBooking(workshopId, title, fee) {
    const modalId = 'workshopModal';
    const workshopIdInput = document.getElementById('bookingWorkshopId');
    const workshopTitleSpan = document.getElementById('bookingWorkshopTitle');
    const workshopFeeSpan = document.getElementById('bookingWorkshopFee');

    if (workshopIdInput) workshopIdInput.value = workshopId;
    if (workshopTitleSpan) workshopTitleSpan.textContent = title;
    if (workshopFeeSpan) workshopFeeSpan.textContent = `LKR ${Number(fee).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;

    openModal(modalId);
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('workshopBookingForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing Application...';

            try {
                const formData = new FormData(form);
                const res = await fetch(`${APP_URL}/api/workshops.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeModal('workshopModal');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.message || 'Application failed', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Failed to connect to server', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // Auto-trigger modal if book_id is present in URL query params
    const urlParams = new URLSearchParams(window.location.search);
    const bookId = urlParams.get('book_id');
    if (bookId) {
        const btn = document.querySelector(`button[onclick*="initiateWorkshopBooking(${bookId},"]`);
        if (btn) btn.click();
    }
});
