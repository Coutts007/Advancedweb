<?php
// navbar.php — Reusable navigation component
// Requires db.php to already be included by the parent page.
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<header class="navbar" id="main-navbar">
    <div class="navbar-inner">

        <!-- Brand / Logo -->
        <a href="index.php" class="navbar-brand" aria-label="A-Commerce Home">
            <span class="brand-icon">🛍️</span>
            <span class="brand-name">A-Commerce</span>
        </a>

        <!-- Desktop Navigation -->
        <nav class="navbar-nav" aria-label="Primary navigation">
            <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'nav-link--active' : '' ?>">
                <span class="nav-icon">🏠</span> Home
            </a>

            <?php if (isLoggedIn()): ?>
                <a href="cart.php" class="nav-link nav-link--cart <?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'nav-link--active' : '' ?>" aria-label="Shopping cart, <?= $cartCount ?> items">
                    <span class="nav-icon">🛒</span> Cart
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge" aria-hidden="true"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>

                <?php if (isAdmin()): ?>
                    <a href="admin-dashboard.php" class="nav-link nav-link--admin <?= basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'nav-link--active' : '' ?>">
                        <span class="nav-icon">📊</span> Admin
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <!-- Auth Controls -->
        <div class="navbar-auth">
            <?php if (isLoggedIn()): ?>
                <div class="user-greeting">
                    <span class="user-avatar" aria-hidden="true">👋</span>
                    <span class="user-name">Welcome, <strong><?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                </div>
                <a href="logout.php" class="btn btn--outline btn--sm" id="logout-btn"
                   onclick="return confirm('Are you sure you want to log out?');">
                    Logout
                </a>
            <?php else: ?>
                <a href="login.php"  class="btn btn--ghost  btn--sm">Sign In</a>
                <a href="signup.php" class="btn btn--primary btn--sm">Register</a>
            <?php endif; ?>
        </div>

        <!-- Mobile hamburger -->
        <button class="hamburger" id="hamburger-btn" aria-label="Toggle mobile menu" aria-expanded="false" aria-controls="mobile-menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Mobile menu drawer -->
    <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
        <nav class="mobile-nav" aria-label="Mobile navigation">
            <a href="index.php" class="mobile-nav-link">🏠 Home</a>

            <?php if (isLoggedIn()): ?>
                <a href="cart.php" class="mobile-nav-link">
                    🛒 Cart <?= $cartCount > 0 ? "($cartCount)" : '' ?>
                </a>

                <?php if (isAdmin()): ?>
                    <a href="admin-dashboard.php" class="mobile-nav-link">
                        📊 Admin Dashboard
                    </a>
                <?php endif; ?>

                <div class="mobile-divider"></div>
                <span class="mobile-user">
                    👋 <?= htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <a href="logout.php" class="mobile-nav-link mobile-nav-link--danger"
                   onclick="return confirm('Are you sure you want to log out?');">
                    🚪 Logout
                </a>
            <?php else: ?>
                <div class="mobile-divider"></div>
                <a href="login.php"  class="mobile-nav-link">Sign In</a>
                <a href="signup.php" class="mobile-nav-link mobile-nav-link--highlight">Register Free</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<script>
// ── Hamburger menu toggle ─────────────────────────────────────
const hamburger   = document.getElementById('hamburger-btn');
const mobileMenu  = document.getElementById('mobile-menu');

hamburger.addEventListener('click', () => {
    const expanded = hamburger.getAttribute('aria-expanded') === 'true';
    hamburger.setAttribute('aria-expanded', String(!expanded));
    mobileMenu.setAttribute('aria-hidden', String(expanded));
    hamburger.classList.toggle('is-open');
    mobileMenu.classList.toggle('is-open');
});

// Close menu on outside click
document.addEventListener('click', (e) => {
    if (!hamburger.contains(e.target) && !mobileMenu.contains(e.target)) {
        hamburger.setAttribute('aria-expanded', 'false');
        mobileMenu.setAttribute('aria-hidden', 'true');
        hamburger.classList.remove('is-open');
        mobileMenu.classList.remove('is-open');
    }
});

// ── Sticky navbar shadow on scroll ───────────────────────────
const navbar = document.getElementById('main-navbar');
window.addEventListener('scroll', () => {
    navbar.classList.toggle('navbar--scrolled', window.scrollY > 10);
}, { passive: true });
</script>
