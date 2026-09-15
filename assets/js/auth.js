/**
 * Authentication and Form Validation
 * Handles live password strength scoring and matching confirmation
 */
document.addEventListener('DOMContentLoaded', () => {
    function setupPasswordValidation(options) {
        const pw = document.getElementById(options.pwId);
        const confirmPw = document.getElementById(options.confirmId);
        const meter = document.getElementById(options.meterId);
        const label = document.getElementById(options.labelId);
        const matchMsg = document.getElementById(options.matchMsgId);
        const form = document.getElementById(options.formId) || (pw ? pw.closest('form') : null);

        // If elements aren't on this page, exit gracefully
        if (!pw || !confirmPw) return;

        function checkStrength() {
            const val = pw.value;
            if (!meter || !label) return 0;

            if (!val || val.length === 0) {
                meter.style.width = '0%';
                label.textContent = '';
                return 0;
            }

            const hasMinLength = val.length >= 8;
            const hasUpper = /[A-Z]/.test(val);
            const hasLower = /[a-z]/.test(val);
            const hasSymbol = /[^A-Za-z0-9]/.test(val);
            const isOnlyDigits = /^\d+$/.test(val);
            const isOnlyLetters = /^[a-zA-Z]+$/.test(val);

            // Rule 1: STRICT WEAK (All digits, all letters, or under 8 chars)
            if (isOnlyDigits || isOnlyLetters || !hasMinLength) {
                meter.style.width = '25%';
                meter.style.backgroundColor = '#dc2626'; // Red
                if (isOnlyDigits) {
                    label.textContent = 'Weak: Cannot be only numbers (must include letters)';
                } else if (isOnlyLetters) {
                    label.textContent = 'Weak: Cannot be only letters (must include numbers)';
                } else {
                    label.textContent = `Too short (${val.length}/8 characters minimum)`;
                }
                label.style.color = '#dc2626';
                return 1;
            }

            // Rule 2: Meets base criteria (8+ chars, mix of letters and numbers)
            let score = 2;
            if (hasUpper && hasLower) score++;
            if (hasSymbol) score++;
            if (val.length >= 12) score++;

            if (score === 2) {
                meter.style.width = '50%';
                meter.style.backgroundColor = '#f59e0b'; // Amber
                label.textContent = 'Fair: Add uppercase letters or symbols';
                label.style.color = '#f59e0b';
                return 2;
            } else if (score === 3) {
                meter.style.width = '75%';
                meter.style.backgroundColor = '#d97706'; // Orange
                label.textContent = 'Good: Mix symbols (!@#$) for maximum security';
                label.style.color = '#d97706';
                return 3;
            } else {
                meter.style.width = '100%';
                meter.style.backgroundColor = '#16a34a'; // Green
                label.textContent = 'Strong password ✓';
                label.style.color = '#16a34a';
                return 4;
            }
        }

        function checkMatch() {
            if (!matchMsg) return false;

            if (!confirmPw.value) {
                matchMsg.style.display = 'none';
                return false;
            }

            if (pw.value === confirmPw.value) {
                matchMsg.style.display = 'block';
                matchMsg.style.color = '#16a34a';
                matchMsg.textContent = '✓ Passwords match';
                return true;
            } else {
                matchMsg.style.display = 'block';
                matchMsg.style.color = '#dc2626';
                matchMsg.textContent = '✗ Passwords do not match';
                return false;
            }
        }

        pw.addEventListener('input', () => {
            checkStrength();
            if (confirmPw.value) checkMatch();
        });

        confirmPw.addEventListener('input', checkMatch);

        // Submit Blocker: Enforce both rules before form leaves the browser
        if (form) {
            form.addEventListener('submit', (e) => {
                const val = pw.value;
                const isOnlyDigits = /^\d+$/.test(val);
                const isOnlyLetters = /^[a-zA-Z]+$/.test(val);

                if (val.length < 8 || isOnlyDigits || isOnlyLetters) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long and contain both letters and numbers.');
                    pw.focus();
                    return;
                }

                if (!checkMatch()) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    confirmPw.focus();
                    return;
                }
            });
        }
    }

    // 1. Automatically binds to Registration Page (if on register.php)
    setupPasswordValidation({
        pwId: 'regPassword',
        confirmId: 'regConfirmPassword',
        meterId: 'pwStrengthMeter',
        labelId: 'pwStrengthLabel',
        matchMsgId: 'pwMatchMsg',
        formId: null // uses closest form
    });

    // 2. Automatically binds to Profile Page (if on profile.php)
    setupPasswordValidation({
        pwId: 'profileNewPassword',
        confirmId: 'profileConfirmPassword',
        meterId: 'profileStrengthMeter',
        labelId: 'profileStrengthLabel',
        matchMsgId: 'profileMatchMsg',
        formId: 'profilePwForm'
    });
});