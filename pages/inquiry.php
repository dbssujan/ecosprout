<?php
$pageTitle = 'Plant Care & Agronomy Inquiries';
require_once __DIR__ . '/../includes/header.php';

$pdo = Database::getConnection();
$recentInquiries = $pdo->query("SELECT * FROM inquiries WHERE status = 'resolved' ORDER BY id DESC LIMIT 3")->fetchAll();
?>

<div class="container" style="padding-top: 2rem;">
    <h1 class="section-title">Plant Care Inquiries & Advisory</h1>
    <p class="section-subtitle">Facing leaf discoloration, pest infestation, or soil drainage issues? Submit your inquiry to our Kegalle nursery botanists.</p>

    <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 3rem; margin-bottom: 3rem;">
        
        <div style="background: var(--surface-white); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
            <h2 style="font-size: 1.3rem; margin-bottom: 1.2rem;">Submit a Plant Care Question</h2>
            <form id="inquiryForm">
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars(current_user()['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Your Email Address</label>
                    <input type="email" name="customer_email" class="form-control" value="<?= htmlspecialchars(current_user()['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Plant Species / Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="e.g. Yellow leaves on Monstera Deliciosa" required>
                </div>
                <div class="form-group">
                    <label>Detailed Description of the Issue</label>
                    <textarea name="message" class="form-control" rows="4" placeholder="Describe sunlight exposure, watering frequency, pot size, visible bugs..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Send to Agronomist</button>
            </form>
        </div>

        
        <div>
            <h2 style="font-size: 1.3rem; margin-bottom: 1.2rem;">Recently Solved Queries</h2>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php if (empty($recentInquiries)): ?>
                    <div style="background: var(--surface-white); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <h4 style="color: var(--primary-dark);">Yellowing leaves on Anthurium?</h4>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 6px;">Often caused by over-watering or poorly draining soil in Kegalle's humid climate. Ensure drainage holes are unblocked and let top 2 inches dry before watering.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentInquiries as $inq): ?>
                        <div style="background: var(--surface-white); padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <h4 style="color: var(--primary-dark);"><?= htmlspecialchars($inq['subject']) ?></h4>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;"><strong>Q:</strong> <?= htmlspecialchars($inq['message']) ?></p>
                            <p style="font-size: 0.85rem; color: var(--primary); margin-top: 8px;"><strong>EcoSprout Agronomist:</strong> <?= htmlspecialchars($inq['staff_reply']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('inquiryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;

    try {
        const formData = new FormData(form);
        const res = await fetch(, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            form.reset();
        } else {
            showToast(data.message || 'Submission failed', 'error');
        }
    } catch(err) {
        showToast('Network error', 'error');
    } finally {
        btn.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
