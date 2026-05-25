<?php
// signup.php — Registration page
require_once 'db.php';

// Already logged in? Go to dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Account — A-Commerce</title>
    <meta name="description" content="Sign up for A-Commerce and start shopping thousands of premium products today." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body class="auth-page">

<div class="auth-container">

    <!-- Brand -->
    <a href="index.php" class="auth-brand">
        <span class="brand-icon">🛍️</span>
        <span class="brand-name">A-Commerce</span>
    </a>

    <div class="auth-card">
        <div class="auth-card-header">
            <h1>Create your account</h1>
            <p>Join thousands of happy shoppers</p>
        </div>

        <!-- Flash messages -->
        <?php if ($flash): ?>
            <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                <span class="alert-icon">
                    <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?>
                </span>
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Client-side validation errors -->
        <div class="alert alert--error" role="alert" id="js-error" style="display:none;">
            <span class="alert-icon">❌</span>
            <span id="js-error-text"></span>
        </div>

        <form id="signup-form" action="signup-handler.php" method="POST" novalidate>
            <!-- CSRF token -->
            <?php
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />

            <div class="form-group">
                <label for="name" class="form-label">Full Name</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        placeholder="Jane Doe"
                        maxlength="120"
                        autocomplete="name"
                        required
                    />
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <span class="input-icon">✉️</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="jane@example.com"
                        maxlength="180"
                        autocomplete="email"
                        required
                    />
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Min. 8 characters"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    />
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility" data-target="password">👁️</button>
                </div>
                <div class="password-strength" id="strength-bar">
                    <div class="strength-fill" id="strength-fill"></div>
                </div>
                <span class="strength-label" id="strength-label"></span>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-input"
                        placeholder="Repeat your password"
                        autocomplete="new-password"
                        required
                    />
                    <button type="button" class="toggle-password" aria-label="Toggle confirm password visibility" data-target="confirm_password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--full" id="signup-btn">
                <span class="btn-text">Create Account</span>
                <span class="btn-loader" style="display:none;">⏳</span>
            </button>
        </form>

        <p class="auth-footer">
            Already have an account?
            <a href="login.php" class="auth-link">Sign in</a>
        </p>
    </div>
</div>

<script>
// ── Password strength meter ───────────────────────────────────
const passwordInput  = document.getElementById('password');
const strengthFill   = document.getElementById('strength-fill');
const strengthLabel  = document.getElementById('strength-label');

function measureStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;
}

passwordInput.addEventListener('input', () => {
    const score = measureStrength(passwordInput.value);
    const pct   = (score / 5) * 100;
    const levels = ['', 'Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    const colors = ['', '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#27ae60'];
    strengthFill.style.width      = pct + '%';
    strengthFill.style.background = colors[score] || '#ddd';
    strengthLabel.textContent     = score > 0 ? levels[score] : '';
    strengthLabel.style.color     = colors[score] || '#aaa';
});

// ── Toggle password visibility ────────────────────────────────
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        target.type  = target.type === 'password' ? 'text' : 'password';
        btn.textContent = target.type === 'password' ? '👁️' : '🙈';
    });
});

// ── Client-side form validation ───────────────────────────────
const form        = document.getElementById('signup-form');
const jsError     = document.getElementById('js-error');
const jsErrorText = document.getElementById('js-error-text');
const signupBtn   = document.getElementById('signup-btn');

function showError(msg) {
    jsErrorText.textContent = msg;
    jsError.style.display   = 'flex';
    jsError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideError() {
    jsError.style.display = 'none';
}

// Validate email format
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

form.addEventListener('submit', (e) => {
    hideError();

    const name     = document.getElementById('name').value.trim();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirm  = document.getElementById('confirm_password').value;

    if (!name || name.length < 2) {
        e.preventDefault();
        return showError('Please enter your full name (at least 2 characters).');
    }

    if (!isValidEmail(email)) {
        e.preventDefault();
        return showError('Please enter a valid email address.');
    }

    if (password.length < 8) {
        e.preventDefault();
        return showError('Password must be at least 8 characters long.');
    }

    if (password !== confirm) {
        e.preventDefault();
        return showError('Passwords do not match. Please try again.');
    }

    // Show loading state
    signupBtn.querySelector('.btn-text').style.display   = 'none';
    signupBtn.querySelector('.btn-loader').style.display = 'inline';
    signupBtn.disabled = true;
});

// Auto-hide flash alerts after 5 s
const flashAlert = document.getElementById('flash-alert');
if (flashAlert) {
    setTimeout(() => {
        flashAlert.style.opacity    = '0';
        flashAlert.style.transition = 'opacity 0.5s';
        setTimeout(() => flashAlert.remove(), 500);
    }, 5000);
}
</script>
</body>
</html>
