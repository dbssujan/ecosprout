<?php
// Initialize session, database connection, and system helpers.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Safe session startup.
start_session_safe();

// Retrieve authenticated user and role context.
$currentUser = current_user();
$userRole = $currentUser['role'] ?? 'guest';
$isCustomer = ($userRole === 'customer');
$isStaff = ($userRole === 'staff');
$isAdmin = ($userRole === 'admin');
$isStaffOrAdmin = ($isStaff || $isAdmin);

// Establish database connection.
$pdo = Database::getConnection();

// Helper to normalize legacy and current status values.
function normalize_inquiry_status($status) {
    if ($status === 'open') return 'pending';
    if ($status === 'resolved') return 'answered';
    return $status;
}

// Fetch inquiries and calculate metrics based on authenticated user role.
$inquiries = [];
$counts = [
    'total'       => 0,
    'waiting'     => 0,
    'pending'     => 0,
    'in_progress' => 0,
    'answered'    => 0,
    'closed'      => 0
];

if ($isStaffOrAdmin) {
    // Staff and administrators access all customer inquiries across the system.
    $stmt = $pdo->prepare("
        SELECT i.*, 
               u_staff.full_name AS staff_name,
               u_cust.id AS registered_user_id
        FROM inquiries i
        LEFT JOIN users u_staff ON i.staff_id = u_staff.id
        LEFT JOIN users u_cust ON i.user_id = u_cust.id
        ORDER BY 
            CASE 
                WHEN (i.status = 'pending' OR i.status = 'open' OR i.staff_reply IS NULL) THEN 0 
                ELSE 1 
            END,
            i.created_at DESC
    ");
    $stmt->execute();
    $inquiries = $stmt->fetchAll();

    foreach ($inquiries as $row) {
        $counts['total']++;
        $normSt = normalize_inquiry_status($row['status']);
        $isWaiting = (empty($row['staff_reply']) || in_array($normSt, ['pending']));
        if ($isWaiting) {
            $counts['waiting']++;
        }
        if (isset($counts[$normSt])) {
            $counts[$normSt]++;
        }
    }
} elseif ($isCustomer) {
    // Customers access strictly their own inquiries without viewing others.
    $stmt = $pdo->prepare("
        SELECT i.*, u.full_name AS staff_name
        FROM inquiries i
        LEFT JOIN users u ON i.staff_id = u.id
        WHERE i.user_id = :uid OR (i.customer_email = :email AND i.customer_email != '')
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([
        ':uid'   => $currentUser['id'],
        ':email' => $currentUser['email']
    ]);
    $inquiries = $stmt->fetchAll();
}

// Fetch published resolved advice for public knowledge base feed.
$publicStmt = $pdo->prepare("
    SELECT i.*, u.full_name AS agronomist_name
    FROM inquiries i
    LEFT JOIN users u ON i.staff_id = u.id
    WHERE (i.status = 'answered' OR i.status = 'resolved') 
      AND i.staff_reply IS NOT NULL 
      AND i.staff_reply != ''
    ORDER BY COALESCE(i.replied_at, i.created_at) DESC
    LIMIT 5
");
$publicStmt->execute();
$publicSolutions = $publicStmt->fetchAll();

// Set page title and load standard header components.
$pageTitle = $isStaffOrAdmin ? "Agronomist Inquiry Management Console - EcoSprout" : "Plant Care Advisory Desk - EcoSprout";
$csrfToken = generate_csrf_token();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Hidden CSRF token for dynamic AJAX operations -->
<input type="hidden" id="globalCsrfToken" value="<?= $csrfToken ?>">

<main class="inquiry-page py-5">
    <div class="container">

        <?php if ($isStaffOrAdmin): ?>
            <!-- ========================================================= -->
            <!-- STAFF & ADMINISTRATOR INQUIRY MANAGEMENT CONSOLE          -->
            <!-- ========================================================= -->
            <div class="mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                    <div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill mb-1">
                            🌿 <?= $isAdmin ? 'Administrator Console' : 'Agronomist Advisory Desk' ?>
                        </span>
                        <h1 class="h3 fw-bold mb-1">Customer Plant Inquiries & Triage Portal</h1>
                        <p class="text-muted small mb-0">
                            Diagnose customer botanical issues, record treatment guidance, and update consultation lifecycle states.
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="#submitConsultationPane" class="btn btn-outline-success btn-sm" data-bs-toggle="collapse" role="button">
                            + Log New Consultation
                        </a>
                    </div>
                </div>

                <!-- Triage Metric Overview Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-2">
                        <div class="card border-0 shadow-sm p-3 text-center bg-white">
                            <span class="text-muted small">Total</span>
                            <span class="h4 fw-bold text-dark mb-0"><?= $counts['total'] ?></span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card border-0 shadow-sm p-3 text-center bg-white border-start border-warning border-4">
                            <span class="text-warning fw-semibold small">Waiting for Response</span>
                            <span class="h4 fw-bold text-warning mb-0" id="metricWaitingCount"><?= $counts['waiting'] ?></span>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="card border-0 shadow-sm p-3 text-center bg-white">
                            <span class="text-muted small">In Progress</span>
                            <span class="h4 fw-bold text-info mb-0"><?= $counts['in_progress'] ?></span>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="card border-0 shadow-sm p-3 text-center bg-white">
                            <span class="text-muted small">Answered</span>
                            <span class="h4 fw-bold text-success mb-0"><?= $counts['answered'] ?></span>
                        </div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="card border-0 shadow-sm p-3 text-center bg-white">
                            <span class="text-muted small">Closed</span>
                            <span class="h4 fw-bold text-secondary mb-0"><?= $counts['closed'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Consultation Submission Drawer for Staff -->
                <div class="collapse mb-4" id="submitConsultationPane">
                    <div class="card border-0 shadow-sm p-4 bg-white">
                        <h2 class="h5 mb-3">Record a Plant Consultation</h2>
                        <div id="inquiryAlertBox" style="display: none;"></div>
                        <form id="inquiryForm" action="<?= APP_URL ?>/api/inquiries.php" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" id="inquiryCsrfToken" value="<?= $csrfToken ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="customer_name" class="form-label small fw-semibold">Customer Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Full name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="customer_email" class="form-label small fw-semibold">Customer Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="customer_email" name="customer_email" placeholder="customer@example.com" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="category" class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="Pest & Disease Diagnosis">Pest & Disease Diagnosis</option>
                                        <option value="Watering & Soil Drainage">Watering & Soil Drainage</option>
                                        <option value="Fertilization & Nutrition">Fertilization & Nutrition</option>
                                        <option value="Pruning & Propagation">Pruning & Propagation</option>
                                        <option value="Indoor Lighting & Climate">Indoor Lighting & Climate</option>
                                        <option value="General Plant Health" selected>General Plant Health</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="subject" class="form-label small fw-semibold">Subject / Plant Species <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="subject" name="subject" placeholder="e.g., Fiddle Leaf Fig foliage discoloration" required>
                                </div>
                                <div class="col-12">
                                    <label for="message" class="form-label small fw-semibold">Observed Symptoms & Conditions <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="3" placeholder="Describe symptoms, watering schedule, lighting conditions..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary" id="submitInquiryBtn">Submit Consultation</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Triage Filter & Search Controls -->
                <div class="card border-0 shadow-sm p-3 mb-4 bg-white">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Status Filters">
                            <button type="button" class="btn btn-primary inquiry-filter-btn active" data-filter="all">All (<?= $counts['total'] ?>)</button>
                            <button type="button" class="btn btn-outline-secondary inquiry-filter-btn" data-filter="waiting">⏳ Waiting for Reply (<?= $counts['waiting'] ?>)</button>
                            <button type="button" class="btn btn-outline-secondary inquiry-filter-btn" data-filter="pending">Pending</button>
                            <button type="button" class="btn btn-outline-secondary inquiry-filter-btn" data-filter="in_progress">In Progress</button>
                            <button type="button" class="btn btn-outline-secondary inquiry-filter-btn" data-filter="answered">Answered</button>
                            <button type="button" class="btn btn-outline-secondary inquiry-filter-btn" data-filter="closed">Closed</button>
                        </div>
                        <div style="min-width: 260px;">
                            <input type="text" class="form-control form-control-sm" id="inquirySearchInput" placeholder="Search customer, email, subject, ID...">
                        </div>
                    </div>
                </div>

                <!-- Inquiry Cards List for Staff / Administrator -->
                <?php if (empty($inquiries)): ?>
                    <div class="card border-0 shadow-sm p-5 text-center bg-white">
                        <span style="font-size: 3rem;">🪴</span>
                        <h3 class="h5 mt-3">No customer inquiries logged</h3>
                        <p class="text-muted small">New inquiries submitted by customers will appear here in real-time.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3" id="staffInquiryContainer">
                        <?php foreach ($inquiries as $iq): 
                            $normSt = normalize_inquiry_status($iq['status']);
                            $isWaiting = (empty($iq['staff_reply']) || in_array($normSt, ['pending']));
                            $cat = $iq['category'] ?? 'General Care';
                        ?>
                            <div class="card border-0 shadow-sm p-4 bg-white staff-inquiry-card" 
                                 id="staff-inquiry-<?= $iq['id'] ?>"
                                 data-id="<?= $iq['id'] ?>"
                                 data-status="<?= $normSt ?>"
                                 data-waiting="<?= $isWaiting ? 'true' : 'false' ?>">
                                <div class="card-body p-0">
                                    <!-- Inquiry Header Row -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <span class="badge bg-light text-muted border font-monospace me-2">#<?= $iq['id'] ?></span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle me-2"><?= htmlspecialchars($cat) ?></span>
                                            <h3 class="h6 fw-bold text-dark d-inline mb-0"><?= htmlspecialchars($iq['subject']) ?></h3>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($isWaiting): ?>
                                                <span class="badge bg-warning text-dark border waiting-status-pill">⏳ Waiting for Response</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle waiting-status-pill">✓ Replied</span>
                                            <?php endif; ?>

                                            <!-- Status Indicator Badge -->
                                            <?php if ($normSt === 'pending'): ?>
                                                <span class="badge bg-warning text-dark status-pill-badge">Pending</span>
                                            <?php elseif ($normSt === 'in_progress'): ?>
                                                <span class="badge bg-info text-dark status-pill-badge">In Progress</span>
                                            <?php elseif ($normSt === 'answered'): ?>
                                                <span class="badge bg-success text-white status-pill-badge">Answered</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary text-white status-pill-badge">Closed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Customer Identification -->
                                    <div class="small text-muted mb-2">
                                        Customer: <strong class="text-dark"><?= htmlspecialchars($iq['customer_name']) ?></strong> 
                                        &bull; Email: <a href="mailto:<?= htmlspecialchars($iq['customer_email']) ?>" class="text-decoration-none"><?= htmlspecialchars($iq['customer_email']) ?></a>
                                        <?php if (!empty($iq['registered_user_id'])): ?>
                                            &bull; <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Registered Customer #<?= $iq['registered_user_id'] ?></span>
                                        <?php else: ?>
                                            &bull; <span class="badge bg-light text-muted border">Guest Submission</span>
                                        <?php endif; ?>
                                        &bull; Submitted: <span><?= date('M d, Y · h:i A', strtotime($iq['created_at'])) ?></span>
                                    </div>

                                    <!-- Customer Symptom Description -->
                                    <div class="p-3 bg-light rounded mb-3 small text-secondary" style="white-space: pre-wrap;">"<?= htmlspecialchars($iq['message']) ?>"</div>

                                    <!-- Permanent Staff Response Display Block -->
                                    <?php if (!empty($iq['staff_reply'])): ?>
                                        <div class="staff-reply-display p-3 bg-light rounded border-start border-success border-3 small mb-3">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="me-2">🩺</span>
                                                <strong class="text-success">Agronomist Response (<?= htmlspecialchars($iq['staff_name'] ?? 'EcoSprout Specialist') ?>)</strong>
                                                <?php if (!empty($iq['replied_at'])): ?>
                                                    <small class="text-muted ms-auto"><?= date('M d, Y · h:i A', strtotime($iq['replied_at'])) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($iq['staff_reply']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Staff & Administrator Action Controls -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                                        <div class="d-flex align-items-center gap-2">
                                            <label class="small text-muted mb-0">Lifecycle Status:</label>
                                            <select class="form-select form-select-sm inquiry-status-select" data-id="<?= $iq['id'] ?>" style="width: auto;">
                                                <option value="pending" <?= $normSt === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="in_progress" <?= $normSt === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="answered" <?= $normSt === 'answered' ? 'selected' : '' ?>>Answered</option>
                                                <option value="closed" <?= $normSt === 'closed' ? 'selected' : '' ?>>Closed</option>
                                            </select>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary open-reply-modal-btn"
                                                    data-id="<?= $iq['id'] ?>"
                                                    data-name="<?= htmlspecialchars($iq['customer_name']) ?>"
                                                    data-subject="<?= htmlspecialchars($iq['subject']) ?>"
                                                    data-message="<?= htmlspecialchars($iq['message']) ?>"
                                                    data-reply="<?= htmlspecialchars($iq['staff_reply'] ?? '') ?>"
                                                    data-status="<?= $normSt ?>">
                                                <?= empty($iq['staff_reply']) ? '💬 Reply to Customer' : '✏️ Update Response' ?>
                                            </button>

                                            <?php if ($isAdmin): ?>
                                                <!-- Administrator Deletion Control -->
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger delete-inquiry-btn"
                                                        data-id="<?= $iq['id'] ?>">
                                                    🗑️ Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Staff Reply Modal Dialog -->
            <div class="modal fade" id="staffReplyModal" tabindex="-1" aria-labelledby="staffReplyModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="staffReplyModalLabel">
                                Agronomist Advisory Reply &bull; <span id="modalInquiryId" class="text-primary font-monospace"></span>
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="staffReplyForm" action="<?= APP_URL ?>/api/inquiries.php" method="POST">
                            <input type="hidden" name="inquiry_id" id="replyModalInquiryId" value="">
                            <input type="hidden" name="csrf_token" id="replyModalCsrfToken" value="<?= $csrfToken ?>">

                            <div class="modal-body">
                                <div class="bg-light p-3 rounded mb-3 small">
                                    <div><strong>Customer:</strong> <span id="modalCustomerName"></span></div>
                                    <div><strong>Topic:</strong> <span id="modalSubject"></span></div>
                                    <hr class="my-2">
                                    <div class="text-secondary" id="modalMessage" style="white-space: pre-wrap;"></div>
                                </div>

                                <div class="mb-3">
                                    <label for="replyModalText" class="form-label small fw-semibold">
                                        Agronomist Diagnosis & Treatment Guidance <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" 
                                              id="replyModalText" 
                                              name="staff_reply" 
                                              rows="6" 
                                              placeholder="Provide diagnosis, recommended watering schedule, fertilizer application, pesticide remedy, or pruning instruction..." 
                                              required></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="replyModalStatus" class="form-label small fw-semibold">Consultation Status</label>
                                        <select class="form-select" id="replyModalStatus" name="status">
                                            <option value="answered" selected>Answered (Solution Provided)</option>
                                            <option value="in_progress">In Progress (Under Review)</option>
                                            <option value="closed">Closed</option>
                                            <option value="pending">Pending</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="saveReplySubmitBtn">Save & Send Agronomist Response</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ========================================================= -->
            <!-- CUSTOMER & GUEST ADVISORY DESK & INQUIRY HISTORY           -->
            <!-- ========================================================= -->
            <div class="text-center mb-5" style="max-width: 720px; margin: 0 auto;">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill mb-2">
                    🌿 Botanical Advisory Desk
                </span>
                <h1 class="h2 fw-bold">Ask Our Certified Agronomists</h1>
                <p class="text-muted">
                    Experiencing foliage discoloration, root rot symptoms, or pest infestations? Submit your botanical symptoms below and receive custom care plans from our Kegalle specialists.
                </p>
            </div>

            <div class="row g-4">
                <!-- Left Column: Customer Consultation Submission Form -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 sticky-top bg-white" style="top: 90px;">
                        <h2 class="h5 mb-2">Submit a Plant Consultation</h2>
                        <p class="text-muted small mb-4">
                            Please describe environmental conditions and visible symptoms for the most accurate diagnosis.
                        </p>

                        <!-- Feedback notification box for AJAX submission -->
                        <div id="inquiryAlertBox" style="display: none;"></div>

                        <form id="inquiryForm" action="<?= APP_URL ?>/api/inquiries.php" method="POST" novalidate>
                            <input type="hidden" name="csrf_token" id="inquiryCsrfToken" value="<?= $csrfToken ?>">

                            <!-- Customer Name Input -->
                            <div class="mb-3">
                                <label for="customer_name" class="form-label small fw-semibold">Your Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="customer_name" 
                                       name="customer_name" 
                                       placeholder="e.g., Poornima Sadaruwani" 
                                       value="<?= htmlspecialchars($currentUser['full_name'] ?? '') ?>" 
                                       required>
                            </div>

                            <!-- Customer Email Input -->
                            <div class="mb-3">
                                <label for="customer_email" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" 
                                       class="form-control" 
                                       id="customer_email" 
                                       name="customer_email" 
                                       placeholder="name@example.com" 
                                       value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" 
                                       required>
                                <div class="form-text">We will link this consultation to your account and send care updates.</div>
                            </div>

                            <!-- Inquiry Category Dropdown -->
                            <div class="mb-3">
                                <label for="category" class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="Pest & Disease Diagnosis">Pest & Disease Diagnosis</option>
                                    <option value="Watering & Soil Drainage">Watering & Soil Drainage</option>
                                    <option value="Fertilization & Nutrition">Fertilization & Nutrition</option>
                                    <option value="Pruning & Propagation">Pruning & Propagation</option>
                                    <option value="Indoor Lighting & Climate">Indoor Lighting & Climate</option>
                                    <option value="General Plant Health" selected>General Plant Health</option>
                                </select>
                            </div>

                            <!-- Inquiry Subject Input -->
                            <div class="mb-3">
                                <label for="subject" class="form-label small fw-semibold">Plant Species or Care Issue <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control" 
                                       id="subject" 
                                       name="subject" 
                                       placeholder="e.g., Monstera deliciosa leaves turning yellow" 
                                       required>
                            </div>

                            <!-- Detailed Symptoms Input -->
                            <div class="mb-4">
                                <label for="message" class="form-label small fw-semibold">Observed Symptoms & Conditions <span class="text-danger">*</span></label>
                                <textarea class="form-control" 
                                          id="message" 
                                          name="message" 
                                          rows="5" 
                                          placeholder="Specify watering frequency, sunlight levels, pot dimensions, fertilizer history, and observed leaf or root changes..." 
                                          required></textarea>
                            </div>

                            <!-- Submit Action Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="submitInquiryBtn">
                                    Send Plant Inquiry
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Customer Own Inquiry History & Verified Solutions Feed -->
                <div class="col-lg-7">
                    <?php if ($isCustomer): ?>
                        <!-- Customer's Own Private Inquiry History -->
                        <div class="mb-5">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h2 class="h5 mb-0">
                                    My Consultation History 
                                    <span id="customerInquiriesCount" class="text-muted small fw-normal">(<?= count($inquiries) ?>)</span>
                                </h2>
                                <span class="badge bg-light text-muted border">Private Customer Log</span>
                            </div>

                            <div id="customerInquiriesList">
                                <?php if (empty($inquiries)): ?>
                                    <div class="card border-0 shadow-sm p-4 text-center bg-white" id="customerInquiriesEmptyState">
                                        <span style="font-size: 2.5rem;">🪴</span>
                                        <h3 class="h6 mt-2">No consultations submitted yet</h3>
                                        <p class="text-muted small mb-0">Use the form on the left to consult our agronomists regarding your plants.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($inquiries as $iq): 
                                        $normSt = normalize_inquiry_status($iq['status']);
                                        $cat = $iq['category'] ?? 'General Care';
                                    ?>
                                        <div class="card border-0 shadow-sm p-4 mb-3 bg-white inquiry-history-item" id="inquiry-card-<?= $iq['id'] ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="badge bg-light text-muted border font-monospace me-2">#<?= $iq['id'] ?></span>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle me-2"><?= htmlspecialchars($cat) ?></span>
                                                    <h3 class="h6 fw-bold text-dark d-inline mb-0"><?= htmlspecialchars($iq['subject']) ?></h3>
                                                </div>

                                                <!-- Read-Only Status Indicator for Customer -->
                                                <?php if ($normSt === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark border">Pending</span>
                                                <?php elseif ($normSt === 'in_progress'): ?>
                                                    <span class="badge bg-info text-dark border">In Progress</span>
                                                <?php elseif ($normSt === 'answered'): ?>
                                                    <span class="badge bg-success text-white border">Answered</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary text-white border">Closed</span>
                                                <?php endif; ?>
                                            </div>

                                            <p class="text-secondary small mb-2" style="white-space: pre-wrap;"><?= htmlspecialchars($iq['message']) ?></p>

                                            <div class="text-muted small mb-3">
                                                Submitted on: <strong><?= date('M d, Y · h:i A', strtotime($iq['created_at'])) ?></strong>
                                            </div>

                                            <!-- Staff Response Read-Only Block -->
                                            <?php if (!empty($iq['staff_reply'])): ?>
                                                <div class="p-3 bg-light rounded border-start border-success border-3 small">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <span class="me-2">🩺</span>
                                                        <strong class="text-success">
                                                            Agronomist Solution (<?= htmlspecialchars($iq['staff_name'] ?? 'EcoSprout Specialist') ?>)
                                                        </strong>
                                                        <?php if (!empty($iq['replied_at'])): ?>
                                                            <small class="text-muted ms-auto"><?= date('M d, Y · h:i A', strtotime($iq['replied_at'])) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($iq['staff_reply']) ?></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="p-3 bg-light rounded border-start border-warning border-3 small">
                                                    <span class="text-muted d-block">
                                                        ⏳ Our Kegalle nursery agronomists are reviewing your inquiry. An advisory response will appear here soon.
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Guest Portal Information Card -->
                        <div class="card border-0 shadow-sm p-4 mb-4 bg-white border-start border-primary border-4">
                            <div class="d-flex align-items-center gap-3">
                                <span style="font-size: 2rem;">👤</span>
                                <div>
                                    <h2 class="h6 fw-bold mb-1">Track Your Botanical History</h2>
                                    <p class="text-muted small mb-2">
                                        Sign in to your EcoSprout account to monitor your plant consultations, receive personalized agronomist guidance, and access your order receipts.
                                    </p>
                                    <a href="<?= APP_URL ?>/pages/login.php" class="btn btn-outline-primary btn-sm">Sign In / Register</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Public Verified Solutions Knowledge Base -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="h5 mb-0">Verified Solutions & Q&A Feed</h2>
                            <span class="badge bg-light text-muted border"><?= count($publicSolutions) ?> Published Guides</span>
                        </div>

                        <?php if (empty($publicSolutions)): ?>
                            <div class="card border-0 shadow-sm text-center p-4 bg-white">
                                <span style="font-size: 2.5rem;">🌱</span>
                                <h3 class="h6 mt-2">No answered inquiries published yet</h3>
                                <p class="text-muted small mb-0">
                                    Be the first to ask a botanical question! Our team reviews and posts verified solutions right here.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($publicSolutions as $qa): 
                                    $cat = $qa['category'] ?? 'General Care';
                                ?>
                                    <div class="card border-0 shadow-sm p-4 bg-white">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle me-2"><?= htmlspecialchars($cat) ?></span>
                                                <h3 class="h6 fw-bold text-dark d-inline mb-0"><?= htmlspecialchars($qa['subject']) ?></h3>
                                            </div>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Resolved</span>
                                        </div>

                                        <p class="text-secondary small mb-2" style="white-space: pre-wrap;">"<?= htmlspecialchars($qa['message']) ?>"</p>

                                        <div class="p-3 bg-light rounded border-start border-success border-3 small">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="me-2">🩺</span>
                                                <strong class="text-success">Agronomist Advice (<?= htmlspecialchars($qa['agronomist_name'] ?? 'EcoSprout Specialist') ?>)</strong>
                                                <?php if (!empty($qa['replied_at'])): ?>
                                                    <small class="text-muted ms-auto"><?= date('M d, Y', strtotime($qa['replied_at'])) ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-dark" style="white-space: pre-wrap;"><?= htmlspecialchars($qa['staff_reply']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- External JavaScript File for Client Side Validation, Submission, and Staff Triage -->
<script src="<?= APP_URL ?>/assets/js/inquiry.js?v=<?= time() ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>