function initiateWorkshopBooking(workshopId, title, fee) {
    const modalId = 'workshopModal';
    const workshopIdInput = document.getElementById('bookingWorkshopId');
    const workshopTitleSpan = document.getElementById('bookingWorkshopTitle');
    const workshopFeeSpan = document.getElementById('bookingWorkshopFee');

    if (workshopIdInput) workshopIdInput.value = workshopId;
    if (workshopTitleSpan) workshopTitleSpan.textContent = title;
    if (workshopFeeSpan) workshopFeeSpan.textContent = `LKR ${Number(fee).toLocaleString()}`;

    openModal(modalId);
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('workshopBookingForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing Registration...';

            try {
                const formData = new FormData(form);
                const res = await fetch(, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeModal('workshopModal');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(data.message || 'Booking failed', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Failed to connect to server', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirm Registration';
            }
        });
    }
});
