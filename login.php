<?php
// login.php — Authentication page
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
    <title>Sign In — A-Commerce</title>
    <meta name="description" content="Sign in to your A-Commerce account to browse and purchase premium products." />
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
            <h1>Welcome back</h1>
            <p>Sign in to your account to continue</p>
        </div>

        <!-- Flash messages -->
        <?php if ($flash): ?>
            <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                <span class="alert-icon">
                    <?php if ($flash['type'] === 'success'): ?>✅
                    <?php elseif ($flash['type'] === 'warning'): ?>⚠️
                    <?php else: ?>❌
                    <?php endif; ?>
                </span>
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Client-side validation errors -->
        <div class="alert alert--error" role="alert" id="js-error" style="display:none;">
            <span class="alert-icon">❌</span>
            <span id="js-error-text"></span>
        </div>

        <form id="login-form" action="login-handler.php" method="POST" novalidate>
            <!-- CSRF token -->
            <?php
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
            ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>" />

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
                        placeholder="Your password"
                        autocomplete="current-password"
                        required
                    />
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility" data-target="password">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--full" id="login-btn">
                <span class="btn-text">Sign In</span>
                <span class="btn-loader" style="display:none;">⏳</span>
            </button>
        </form>

        <p class="auth-footer">
            Don't have an account?
            <a href="signup.php" class="auth-link">Create one free</a>
        </p>
    </div>
</div>

<script>
// ── Toggle password visibility ────────────────────────────────
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        target.type  = target.type === 'password' ? 'text' : 'password';
        btn.textContent = target.type === 'password' ? '👁️' : '🙈';
    });
});

// ── Client-side validation ────────────────────────────────────
const form        = document.getElementById('login-form');
const jsError     = document.getElementById('js-error');
const jsErrorText = document.getElementById('js-error-text');
const loginBtn    = document.getElementById('login-btn');

function showError(msg) {
    jsErrorText.textContent = msg;
    jsError.style.display   = 'flex';
    jsError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

form.addEventListener('submit', (e) => {
    jsError.style.display = 'none';

    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    if (!isValidEmail(email)) {
        e.preventDefault();
        return showError('Please enter a valid email address.');
    }

    if (!password) {
        e.preventDefault();
        return showError('Please enter your password.');
    }

    // Loading state
    loginBtn.querySelector('.btn-text').style.display   = 'none';
    loginBtn.querySelector('.btn-loader').style.display = 'inline';
    loginBtn.disabled = true;
});

// Auto-hide flash alerts
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
