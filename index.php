<?php
// index.php — Protected homepage / product listing
require_once 'db.php';

// Enforce authentication
requireLogin();

// ── Handle "Add to Cart" AJAX / form POST ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    header('Content-Type: application/json');

    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if (!$productId || $productId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    // Verify product exists and has stock
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT id, title, stock FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $currentQty = $_SESSION['cart'][$productId] ?? 0;
    if ($currentQty >= $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'No more stock available.']);
        exit;
    }

    $_SESSION['cart'][$productId] = $currentQty + 1;
    $cartTotal = array_sum($_SESSION['cart']);

    echo json_encode([
        'success'    => true,
        'message'    => htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') . ' added to cart!',
        'cart_count' => $cartTotal,
    ]);
    exit;
}

// ── Fetch all products ────────────────────────────────────────
$pdo      = getPDO();
$stmt     = $pdo->query('SELECT id, title, description, price, image_url, stock FROM products ORDER BY id ASC');
$products = $stmt->fetchAll();

// Generate CSRF token for cart AJAX calls
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shop — A-Commerce</title>
    <meta name="description" content="Browse thousands of premium products on A-Commerce. Find the best deals on electronics, fashion, home goods, and more." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body class="shop-page">

<?php include 'navbar.php'; ?>

<main id="main-content">

    <!-- ── Hero Section ───────────────────────────────────── -->
    <section class="hero" aria-labelledby="hero-heading">
        <div class="hero-bg-shapes" aria-hidden="true">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
        <div class="hero-content container">
            <div class="hero-badge">✨ New arrivals every week</div>
            <h1 id="hero-heading" class="hero-title">
                Shop <span class="hero-highlight">Smarter.</span><br>
                Live <span class="hero-highlight">Better.</span>
            </h1>
            <p class="hero-subtitle">
                Discover curated collections of premium products across electronics, fashion, home, and more — all in one place.
            </p>
            <div class="hero-actions">
                <a href="#products-section" class="btn btn--primary btn--lg hero-cta">
                    Shop Now <span aria-hidden="true">→</span>
                </a>
                <div class="hero-stats">
                    <div class="stat">
                        <strong>8+</strong>
                        <span>Products</span>
                    </div>
                    <div class="stat-divider" aria-hidden="true"></div>
                    <div class="stat">
                        <strong>100%</strong>
                        <span>Secure</span>
                    </div>
                    <div class="stat-divider" aria-hidden="true"></div>
                    <div class="stat">
                        <strong>Fast</strong>
                        <span>Delivery</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Toast notification ─────────────────────────────── -->
    <div class="toast" id="cart-toast" role="status" aria-live="polite" aria-atomic="true">
        <span class="toast-icon">🛒</span>
        <span class="toast-text" id="toast-text"></span>
    </div>

    <!-- ── Flash message ──────────────────────────────────── -->
    <?php if ($flash): ?>
        <div class="container" style="margin-top: 1rem;">
            <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── Products Section ───────────────────────────────── -->
    <section class="products-section" id="products-section" aria-labelledby="products-heading">
        <div class="container">
            <div class="section-header">
                <h2 id="products-heading" class="section-title">Featured Products</h2>
                <p class="section-subtitle">Handpicked selections for the discerning shopper</p>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">📦</span>
                    <h3>No products yet</h3>
                    <p>Check back soon — new items are being added.</p>
                </div>
            <?php else: ?>
                <div class="product-grid" id="product-grid">
                    <?php foreach ($products as $product): ?>
                        <?php
                            $inStock    = $product['stock'] > 0;
                            $lowStock   = $product['stock'] > 0 && $product['stock'] <= 5;
                            $cartQty    = $_SESSION['cart'][$product['id']] ?? 0;
                            $maxReached = $cartQty >= $product['stock'];
                        ?>
                        <article
                            class="product-card"
                            id="product-card-<?= (int) $product['id'] ?>"
                            data-product-id="<?= (int) $product['id'] ?>"
                            aria-label="<?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <!-- Product Image -->
                            <div class="card-image-wrap">
                                <img
                                    src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="card-image"
                                    loading="lazy"
                                    onerror="this.src='https://picsum.photos/seed/fallback/600/400'; this.onerror=null;"
                                />
                                <!-- Stock badge -->
                                <?php if (!$inStock): ?>
                                    <div class="card-badge card-badge--out">Out of Stock</div>
                                <?php elseif ($lowStock): ?>
                                    <div class="card-badge card-badge--low">Only <?= (int) $product['stock'] ?> left!</div>
                                <?php endif; ?>
                            </div>

                            <!-- Product Info -->
                            <div class="card-body">
                                <h3 class="card-title"><?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="card-desc"><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></p>

                                <div class="card-footer">
                                    <div class="card-price-block">
                                        <span class="card-price">$<?= number_format((float) $product['price'], 2) ?></span>
                                        <span class="card-stock <?= $inStock ? 'card-stock--in' : 'card-stock--out' ?>">
                                            <?= $inStock ? '✅ In Stock' : '❌ Sold Out' ?>
                                        </span>
                                    </div>

                                    <button
                                        class="btn btn--primary btn--sm add-to-cart-btn"
                                        data-product-id="<?= (int) $product['id'] ?>"
                                        <?= (!$inStock || $maxReached) ? 'disabled' : '' ?>
                                        aria-label="Add <?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?> to cart"
                                    >
                                        <?php if (!$inStock): ?>
                                            Sold Out
                                        <?php elseif ($maxReached): ?>
                                            Max in Cart
                                        <?php else: ?>
                                            <span>🛒</span> Add to Cart
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<!-- ── Footer ────────────────────────────────────────────────── -->
<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <span class="brand-icon">🛍️</span>
            <span class="brand-name">A-Commerce</span>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> A-Commerce. All rights reserved.</p>
        <div class="footer-links">
            <a href="#" class="footer-link">Privacy</a>
            <a href="#" class="footer-link">Terms</a>
            <a href="#" class="footer-link">Contact</a>
        </div>
    </div>
</footer>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

// ── Add to Cart (async) ───────────────────────────────────────
document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const productId = btn.dataset.productId;
        const originalHTML = btn.innerHTML;

        // Visual loading state
        btn.disabled  = true;
        btn.innerHTML = '⏳ Adding...';

        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:     'add_to_cart',
                    product_id: productId,
                    csrf_token: CSRF_TOKEN,
                }),
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message);
                updateCartBadge(data.cart_count);
                btn.innerHTML = '✅ Added!';
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled  = false;
                }, 1800);
            } else {
                showToast(data.message, 'error');
                btn.innerHTML = originalHTML;
                btn.disabled  = (btn.textContent.trim() === 'Sold Out' || btn.textContent.trim() === 'Max in Cart');
            }
        } catch (err) {
            console.error(err);
            showToast('Something went wrong. Please try again.', 'error');
            btn.innerHTML = originalHTML;
            btn.disabled  = false;
        }
    });
});

// ── Toast Notification ────────────────────────────────────────
let toastTimer;
function showToast(message, type = 'success') {
    const toast    = document.getElementById('cart-toast');
    const toastTxt = document.getElementById('toast-text');

    toastTxt.textContent    = message;
    toast.dataset.type      = type;
    toast.querySelector('.toast-icon').textContent = type === 'error' ? '❌' : '🛒';

    toast.classList.add('toast--visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('toast--visible'), 3000);
}

// ── Cart badge counter ────────────────────────────────────────
function updateCartBadge(count) {
    let badge = document.querySelector('.cart-badge');
    const cartLink = document.querySelector('.nav-link--cart');
    if (!cartLink) return;

    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className   = 'cart-badge';
            badge.setAttribute('aria-hidden', 'true');
            cartLink.appendChild(badge);
        }
        badge.textContent = count;
    } else if (badge) {
        badge.remove();
    }
}

// ── Smooth scroll to products ─────────────────────────────────
document.querySelector('.hero-cta')?.addEventListener('click', (e) => {
    const target = document.querySelector('#products-section');
    if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth' });
    }
});

// ── Card entrance animation on scroll ────────────────────────
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('card--visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.08 });

document.querySelectorAll('.product-card').forEach(card => observer.observe(card));

// Auto-dismiss flash alert
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
