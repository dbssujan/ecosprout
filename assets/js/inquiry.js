// Client-side inquiry submission, staff response workflows, and real-time triage controls for EcoSprout.

document.addEventListener('DOMContentLoaded', function () {
    // Initialize customer inquiry submission form.
    initInquiryForm();

    // Initialize staff and administrator triage controls.
    initStaffManagement();
});

// Handle asynchronous submission of plant care inquiries.
function initInquiryForm() {
    const form = document.getElementById('inquiryForm');
    const alertBox = document.getElementById('inquiryAlertBox');
    const submitBtn = document.getElementById('submitInquiryBtn');

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Extract and trim form inputs.
        const name = document.getElementById('customer_name')?.value.trim() || '';
        const email = document.getElementById('customer_email')?.value.trim() || '';
        const category = document.getElementById('category')?.value.trim() || 'General Care';
        const subject = document.getElementById('subject')?.value.trim() || '';
        const message = document.getElementById('message')?.value.trim() || '';
        const csrfToken = document.getElementById('inquiryCsrfToken')?.value || '';

        // Validate mandatory input presence.
        if (!name || !email || !subject || !message) {
            displayInquiryAlert('Please fill in all required fields before submitting.', 'danger', alertBox);
            return;
        }

        // Validate customer email address pattern.
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            displayInquiryAlert('Please provide a valid email address.', 'danger', alertBox);
            return;
        }

        // Indicate loading state on button to prevent duplicate submissions.
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting Consultation...';
        }

        // Prepare form data payload.
        const formData = new FormData();
        formData.append('action', 'submit');
        formData.append('customer_name', name);
        formData.append('customer_email', email);
        formData.append('category', category);
        formData.append('subject', subject);
        formData.append('message', message);
        formData.append('csrf_token', csrfToken);

        const targetUrl = form.getAttribute('action') || '/ecosprout/api/inquiries.php';

        // Dispatch asynchronous POST request to inquiry API.
        fetch(targetUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                displayInquiryAlert(data.message, 'success', alertBox);

                // Reset symptom inputs while preserving customer identification.
                document.getElementById('subject').value = '';
                document.getElementById('message').value = '';

                // Automatically prepend the newly registered inquiry into customer history view.
                if (data.inquiry) {
                    appendInquiryToCustomerHistory(data.inquiry);
                }

                if (typeof showToast === 'function') {
                    showToast(data.message, 'success');
                }
            } else {
                displayInquiryAlert(data.message || 'Unable to submit your inquiry.', 'danger', alertBox);
            }
        })
        .catch(error => {
            console.error('Inquiry submission error:', error);
            displayInquiryAlert('Network error occurred. Please try again later.', 'danger', alertBox);
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Send Plant Inquiry';
            }
        });
    });
}

// Automatically append new customer inquiry into active customer history container.
function appendInquiryToCustomerHistory(inquiry) {
    const historyContainer = document.getElementById('customerInquiriesList');
    const emptyState = document.getElementById('customerInquiriesEmptyState');
    const countBadge = document.getElementById('customerInquiriesCount');

    if (!historyContainer) return;

    if (emptyState) {
        emptyState.remove();
    }

    if (countBadge) {
        const currentCount = parseInt(countBadge.textContent.replace(/\D/g, '') || '0', 10);
        countBadge.textContent = '(' + (currentCount + 1) + ')';
    }

    const card = document.createElement('div');
    card.className = 'card border-0 shadow-sm p-4 mb-3 bg-white inquiry-history-item';
    card.id = 'inquiry-card-' + inquiry.id;
    card.innerHTML = 
        '<div class="d-flex justify-content-between align-items-start mb-2">' +
            '<div>' +
                '<span class="badge bg-light text-muted border font-monospace me-2">#' + escapeHtml(String(inquiry.id)) + '</span>' +
                '<span class="badge bg-success-subtle text-success border border-success-subtle me-2">' + escapeHtml(inquiry.category || 'General Care') + '</span>' +
                '<h3 class="h6 fw-bold text-dark d-inline mb-0">' + escapeHtml(inquiry.subject) + '</h3>' +
            '</div>' +
            '<span class="badge bg-warning text-dark border">Pending</span>' +
        '</div>' +
        '<p class="text-secondary small mb-2" style="white-space: pre-wrap;">' + escapeHtml(inquiry.message) + '</p>' +
        '<div class="text-muted small mb-3">' +
            'Submitted on: <strong>' + escapeHtml(inquiry.formatted_date || 'Just now') + '</strong>' +
        '</div>' +
        '<div class="p-3 bg-light rounded border-start border-warning border-3 small">' +
            '<span class="text-muted d-block">' +
                '⏳ Our Kegalle nursery agronomists are reviewing your inquiry. An advisory response will appear here soon.' +
            '</span>' +
        '</div>';

    historyContainer.prepend(card);
}

// Initialize staff and administrator interaction controls.
function initStaffManagement() {
    const replyModalEl = document.getElementById('staffReplyModal');
    const replyForm = document.getElementById('staffReplyForm');
    const searchInput = document.getElementById('inquirySearchInput');
    const filterButtons = document.querySelectorAll('.inquiry-filter-btn');

    // Handle opening reply dialog and populating consultation context.
    document.addEventListener('click', function (e) {
        const replyBtn = e.target.closest('.open-reply-modal-btn');
        if (replyBtn) {
            const id = replyBtn.getAttribute('data-id');
            const name = replyBtn.getAttribute('data-name');
            const subject = replyBtn.getAttribute('data-subject');
            const message = replyBtn.getAttribute('data-message');
            const reply = replyBtn.getAttribute('data-reply') || '';
            const status = replyBtn.getAttribute('data-status') || 'answered';

            const modalInquiryId = document.getElementById('modalInquiryId');
            const modalCustomerName = document.getElementById('modalCustomerName');
            const modalSubject = document.getElementById('modalSubject');
            const modalMessage = document.getElementById('modalMessage');
            const modalFormId = document.getElementById('replyModalInquiryId');
            const modalTextarea = document.getElementById('replyModalText');
            const modalStatusSelect = document.getElementById('replyModalStatus');

            if (modalInquiryId) modalInquiryId.textContent = '#' + id;
            if (modalCustomerName) modalCustomerName.textContent = name;
            if (modalSubject) modalSubject.textContent = subject;
            if (modalMessage) modalMessage.textContent = message;
            if (modalFormId) modalFormId.value = id;
            if (modalTextarea) modalTextarea.value = reply;
            if (modalStatusSelect) modalStatusSelect.value = status === 'pending' ? 'answered' : status;

            if (typeof bootstrap !== 'undefined' && replyModalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(replyModalEl);
                modal.show();
            } else if (replyModalEl) {
                replyModalEl.style.display = 'block';
                replyModalEl.classList.add('show');
            }
        }

        // Handle administrator deletion request.
        const deleteBtn = e.target.closest('.delete-inquiry-btn');
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            if (confirm('Are you sure you want to permanently delete Inquiry #' + id + '?')) {
                deleteInquiry(id, deleteBtn);
            }
        }
    });

    // Close modal when close button is clicked without bootstrap dependency.
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function () {
            if (replyModalEl) {
                replyModalEl.style.display = 'none';
                replyModalEl.classList.remove('show');
            }
        });
    });

    // Handle submission of agronomist response.
    if (replyForm) {
        replyForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const inquiryId = document.getElementById('replyModalInquiryId')?.value;
            const replyText = document.getElementById('replyModalText')?.value.trim();
            const statusVal = document.getElementById('replyModalStatus')?.value || 'answered';
            const csrfToken = document.getElementById('replyModalCsrfToken')?.value || '';
            const submitReplyBtn = document.getElementById('saveReplySubmitBtn');

            if (!inquiryId || !replyText) {
                alert('Please provide a response message before saving.');
                return;
            }

            if (submitReplyBtn) {
                submitReplyBtn.disabled = true;
                submitReplyBtn.innerHTML = 'Saving Agronomist Advice...';
            }

            const formData = new FormData();
            formData.append('action', 'reply');
            formData.append('inquiry_id', inquiryId);
            formData.append('staff_reply', replyText);
            formData.append('status', statusVal);
            formData.append('csrf_token', csrfToken);

            fetch(replyForm.getAttribute('action') || '/ecosprout/api/inquiries.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update DOM card in staff console.
                    updateStaffInquiryCard(data);

                    if (typeof bootstrap !== 'undefined' && replyModalEl) {
                        const modalInstance = bootstrap.Modal.getInstance(replyModalEl);
                        if (modalInstance) modalInstance.hide();
                    } else if (replyModalEl) {
                        replyModalEl.style.display = 'none';
                    }

                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                } else {
                    alert(data.message || 'Failed to save staff response.');
                }
            })
            .catch(err => {
                console.error('Reply submission failed:', err);
                alert('Network error while saving response.');
            })
            .finally(() => {
                if (submitReplyBtn) {
                    submitReplyBtn.disabled = false;
                    submitReplyBtn.innerHTML = 'Save & Send Agronomist Response';
                }
            });
        });
    }

    // Handle immediate inline status select updates.
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('inquiry-status-select')) {
            const selectEl = e.target;
            const inquiryId = selectEl.getAttribute('data-id');
            const newStatus = selectEl.value;
            const csrfToken = document.getElementById('globalCsrfToken')?.value || '';

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('inquiry_id', inquiryId);
            formData.append('status', newStatus);
            formData.append('csrf_token', csrfToken);

            selectEl.disabled = true;

            fetch('/ecosprout/api/inquiries.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const card = document.getElementById('staff-inquiry-' + inquiryId);
                    if (card) {
                        card.setAttribute('data-status', newStatus);
                        const badge = card.querySelector('.status-pill-badge');
                        if (badge) {
                            applyStatusBadgeStyle(badge, newStatus);
                        }
                    }
                    if (typeof showToast === 'function') {
                        showToast(data.message, 'success');
                    }
                } else {
                    alert(data.message || 'Failed to update inquiry status.');
                }
            })
            .catch(err => {
                console.error('Status update failed:', err);
                alert('Network error while updating status.');
            })
            .finally(() => {
                selectEl.disabled = false;
            });
        }
    });

    // Handle filter pills for staff triage.
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            filterButtons.forEach(b => b.classList.remove('active', 'btn-primary'));
            filterButtons.forEach(b => b.classList.add('btn-outline-secondary'));

            this.classList.add('active', 'btn-primary');
            this.classList.remove('btn-outline-secondary');

            const filterValue = this.getAttribute('data-filter') || 'all';
            filterInquiryCards(filterValue, searchInput?.value || '');
        });
    });

    // Handle live keyword search across customer inquiries.
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const activeFilter = document.querySelector('.inquiry-filter-btn.active')?.getAttribute('data-filter') || 'all';
            filterInquiryCards(activeFilter, this.value.trim().toLowerCase());
        });
    }
}

// Perform instant filtering and search across staff consultation list.
function filterInquiryCards(filter, searchQuery) {
    const cards = document.querySelectorAll('.staff-inquiry-card');

    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status') || '';
        const isWaiting = card.getAttribute('data-waiting') === 'true';
        const cardText = card.textContent.toLowerCase();

        let matchesFilter = false;
        if (filter === 'all') {
            matchesFilter = true;
        } else if (filter === 'waiting') {
            matchesFilter = isWaiting;
        } else if (filter === cardStatus) {
            matchesFilter = true;
        }

        const matchesSearch = !searchQuery || cardText.includes(searchQuery);

        if (matchesFilter && matchesSearch) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

// Update DOM elements on staff consultation card after response is submitted.
function updateStaffInquiryCard(data) {
    const card = document.getElementById('staff-inquiry-' + data.inquiry_id);
    if (!card) return;

    card.setAttribute('data-status', data.status_updated);
    card.setAttribute('data-waiting', 'false');

    // Update status badge.
    const badge = card.querySelector('.status-pill-badge');
    if (badge) {
        applyStatusBadgeStyle(badge, data.status_updated);
    }

    // Update status select dropdown.
    const select = card.querySelector('.inquiry-status-select');
    if (select) {
        select.value = data.status_updated;
    }

    // Update waiting indicator pill.
    const waitingPill = card.querySelector('.waiting-status-pill');
    if (waitingPill) {
        waitingPill.className = 'badge bg-success-subtle text-success border border-success-subtle waiting-status-pill';
        waitingPill.textContent = '✓ Replied';
    }

    // Update or insert staff response block.
    let responseContainer = card.querySelector('.staff-reply-display');
    if (!responseContainer) {
        responseContainer = document.createElement('div');
        responseContainer.className = 'staff-reply-display p-3 bg-light rounded border-start border-success border-3 small mb-3';
        const bodyEl = card.querySelector('.card-body');
        const actionsEl = bodyEl.querySelector('.border-top');
        if (bodyEl && actionsEl) {
            bodyEl.insertBefore(responseContainer, actionsEl);
        } else if (bodyEl) {
            bodyEl.appendChild(responseContainer);
        }
    }

    responseContainer.innerHTML = 
        '<div class="d-flex align-items-center mb-1">' +
            '<span class="me-2">🩺</span>' +
            '<strong class="text-success">Agronomist Response (' + escapeHtml(data.staff_name) + ')</strong>' +
            '<small class="text-muted ms-auto">' + escapeHtml(data.formatted_date) + '</small>' +
        '</div>' +
        '<div class="text-dark" style="white-space: pre-wrap;">' + escapeHtml(data.staff_reply) + '</div>';

    // Update reply button data attributes.
    const replyBtn = card.querySelector('.open-reply-modal-btn');
    if (replyBtn) {
        replyBtn.setAttribute('data-reply', data.staff_reply);
        replyBtn.setAttribute('data-status', data.status_updated);
        replyBtn.innerHTML = '✏️ Update Response';
    }
}

// Apply styling to status badges.
function applyStatusBadgeStyle(badgeEl, status) {
    badgeEl.className = 'badge status-pill-badge ';
    if (status === 'pending') {
        badgeEl.className += 'bg-warning text-dark';
        badgeEl.textContent = 'Pending';
    } else if (status === 'in_progress') {
        badgeEl.className += 'bg-info text-dark';
        badgeEl.textContent = 'In Progress';
    } else if (status === 'answered') {
        badgeEl.className += 'bg-success text-white';
        badgeEl.textContent = 'Answered';
    } else if (status === 'closed') {
        badgeEl.className += 'bg-secondary text-white';
        badgeEl.textContent = 'Closed';
    }
}

// Delete inquiry via administrator action.
function deleteInquiry(id, buttonEl) {
    const csrfToken = document.getElementById('globalCsrfToken')?.value || '';
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('inquiry_id', id);
    formData.append('csrf_token', csrfToken);

    buttonEl.disabled = true;

    fetch('/ecosprout/api/inquiries.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const card = document.getElementById('staff-inquiry-' + id);
            if (card) {
                card.remove();
            }
            if (typeof showToast === 'function') {
                showToast(data.message, 'success');
            } else {
                alert(data.message);
            }
        } else {
            alert(data.message || 'Failed to delete inquiry.');
            buttonEl.disabled = false;
        }
    })
    .catch(err => {
        console.error('Delete failed:', err);
        alert('Network error while deleting inquiry.');
        buttonEl.disabled = false;
    });
}

// Utility helper to render notification banners.
function displayInquiryAlert(msg, type, container) {
    if (!container) return;
    container.className = 'alert alert-' + type + ' alert-dismissible fade show';
    container.innerHTML = 
        msg + 
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    container.style.display = 'block';
}

// Utility helper to escape HTML characters.
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}