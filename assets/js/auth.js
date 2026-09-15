/**
 * Authentication and Form Validation (Vanilla JS)
 * Handles live password strength scoring and matching confirmation
 */
document.addEventListener('DOMContentLoaded', () => {
    const pw = document.getElementById('regPassword');
    const confirmPw = document.getElementById('regConfirmPassword');
    const strengthMeter = document.getElementById('pwStrengthMeter');
    const strengthLabel = document.getElementById('pwStrengthLabel');
    const matchMsg = document.getElementById('pwMatchMsg');
    const form = pw ? pw.closest('form') : null;

    if (!pw || !confirmPw) return;

    // Evaluates password strength on a scale of 0 to 4
    function calculateStrength(password) {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        return score;
    }

    // Updates live strength meter bar and text
    function updateStrength() {
        const val = pw.value;
        const score = calculateStrength(val);

        if (!val) {
            if (strengthMeter) strengthMeter.style.width = '0%';
            if (strengthLabel) strengthLabel.textContent = '';
            return 0;
        }

        const levels = [
            { width: '25%', color: 'var(--danger)', text: 'Weak (needs 8+ Charactrer, numbers, symbols)' },
            { width: '50%', color: 'var(--warning)', text: 'Fair (add numbers or uppercase)' },
            { width: '75%', color: 'var(--accent-orange, #f59e0b)', text: 'Good' },
            { width: '100%', color: 'var(--success)', text: 'Strong password' }
        ];

        const current = levels[Math.max(0, score - 1)];

        if (strengthMeter) {
            strengthMeter.style.width = current.width;
            strengthMeter.style.backgroundColor = current.color;
        }

        if (strengthLabel) {
            strengthLabel.textContent = current.text;
            strengthLabel.style.color = current.color;
        }

        return score;
    }

    // Checks whether both password fields match
    function checkMatch() {
        if (!confirmPw.value) {
            if (matchMsg) matchMsg.style.display = 'none';
            return false;
        }

        if (pw.value === confirmPw.value) {
            if (matchMsg) {
                matchMsg.style.display = 'block';
                matchMsg.style.color = 'var(--success)';
                matchMsg.textContent = '✓ Passwords match';
            }
            return true;
        } else {
            if (matchMsg) {
                matchMsg.style.display = 'block';
                matchMsg.style.color = 'var(--danger)';
                matchMsg.textContent = '✗ Passwords do not match';
            }
            return false;
        }
    }

    pw.addEventListener('input', () => {
        updateStrength();
        if (confirmPw.value) checkMatch();
    });

    confirmPw.addEventListener('input', checkMatch);

    // Form submission guard
    if (form) {
        form.addEventListener('submit', (e) => {
            const score = updateStrength();
            const matches = checkMatch();

            if (pw.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                pw.focus();
                return;
            }

            if (!matches) {
                e.preventDefault();
                alert('Passwords do not match.');
                confirmPw.focus();
            }
        });
    }
});