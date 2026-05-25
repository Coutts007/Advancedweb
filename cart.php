<?php
// cart.php — Shopping cart page
require_once 'db.php';

// Enforce authentication
requireLogin();

// Handle cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    // Validate CSRF token
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        setFlash('warning', 'Invalid security token.');
        redirect('cart.php');
    }

    if ($action === 'remove_item') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($productId && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            setFlash('success', 'Item removed from cart.');
        }
        redirect('cart.php');
    }

    if ($action === 'update_quantity') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        if ($productId && $quantity !== false) {
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$productId]);
                setFlash('success', 'Item removed from cart.');
            } else {
                // Verify product exists and has stock
                $pdo = getPDO();
                $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ? LIMIT 1');
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if ($product && $quantity <= $product['stock']) {
                    $_SESSION['cart'][$productId] = $quantity;
                    setFlash('success', 'Cart updated.');
                } else {
                    setFlash('warning', 'Invalid quantity or insufficient stock.');
                }
            }
        }
        redirect('cart.php');
    }

    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        setFlash('success', 'Cart cleared.');
        redirect('cart.php');
    }

    if ($action === 'checkout') {
        if (empty($_SESSION['cart'])) {
            setFlash('warning', 'Your cart is empty.');
        } else {
            setFlash('success', 'Order placed successfully! Thank you for your purchase.');
            $_SESSION['cart'] = [];
        }
        redirect('cart.php');
    }
}

// Fetch cart items
$cartItems = [];
$cartTotal = 0;

if (!empty($_SESSION['cart'])) {
    $pdo = getPDO();
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    
    $stmt = $pdo->prepare("SELECT id, title, price, image_url, stock FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        $itemTotal = $product['price'] * $quantity;
        $cartTotal += $itemTotal;

        $cartItems[] = [
            'id' => $product['id'],
            'title' => $product['title'],
            'price' => $product['price'],
            'image_url' => $product['image_url'],
            'stock' => $product['stock'],
            'quantity' => $quantity,
            'itemTotal' => $itemTotal,
        ];
    }
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Shopping Cart — A-Commerce</title>
    <meta name="description" content="Review your shopping cart and proceed to checkout." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body class="cart-page">

<?php include 'navbar.php'; ?>

<main id="main-content">
    <section class="cart-section" aria-labelledby="cart-heading">
        <div class="container">
            <div class="section-header">
                <h1 id="cart-heading" class="section-title">Shopping Cart</h1>
                <p class="section-subtitle">Review and manage your items</p>
            </div>

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                    <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '⚠️' ?></span>
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart State -->
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true">🛒</span>
                    <h2>Your cart is empty</h2>
                    <p>Start shopping to add items to your cart.</p>
                    <a href="index.php" class="btn btn--primary btn--lg">
                        Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <!-- Cart Content -->
                <div class="cart-layout">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div class="cart-items-header">
                            <span><?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?> in cart</span>
                        </div>

                        <div class="cart-item-list">
                            <?php foreach ($cartItems as $item): ?>
                                <article class="cart-item" data-product-id="<?= (int) $item['id'] ?>">
                                    <!-- Item Image -->
                                    <div class="cart-item-image-wrapper">
                                        <img
                                            src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="cart-item-image"
                                            loading="lazy"
                                            onerror="this.src='https://picsum.photos/seed/fallback/200/200'; this.onerror=null;"
                                        />
                                    </div>

                                    <!-- Item Details -->
                                    <div class="cart-item-details">
                                        <h3 class="cart-item-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <div class="cart-item-price-stock">
                                            <span class="cart-item-price">$<?= number_format((float) $item['price'], 2) ?></span>
                                            <span class="cart-item-stock-info">Stock: <?= (int) $item['stock'] ?></span>
                                        </div>
                                    </div>

                                    <!-- Quantity Controls -->
                                    <div class="cart-item-actions">
                                        <form method="POST" action="cart.php" class="quantity-form" onsubmit="return validateQuantity(this)">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            
                                            <div class="quantity-controls">
                                                <span class="qty-label">Qty:</span>
                                                <div class="quantity-input-group">
                                                    <button type="submit" name="adjust" value="minus" class="qty-btn qty-btn--minus" aria-label="Decrease quantity">−</button>
                                                    <input 
                                                        type="number" 
                                                        name="quantity" 
                                                        value="<?= (int) $item['quantity'] ?>" 
                                                        min="1" 
                                                        max="<?= (int) $item['stock'] ?>"
                                                        class="qty-input"
                                                        aria-label="Quantity"
                                                    >
                                                    <button type="submit" name="adjust" value="plus" class="qty-btn qty-btn--plus" aria-label="Increase quantity">+</button>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- Item Total -->
                                        <div class="cart-item-total">
                                            <span class="cart-item-total-label">Total</span>
                                            <span class="cart-item-total-amount">$<?= number_format((float) $item['itemTotal'], 2) ?></span>
                                        </div>

                                        <!-- Remove Button -->
                                        <form method="POST" action="cart.php" class="cart-item-remove-form" onsubmit="return confirm('Remove this item from cart?')">
                                            <input type="hidden" name="action" value="remove_item">
                                            <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn-remove" aria-label="Remove from cart" title="Remove from cart">
                                                <span>🗑️</span>
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <aside class="cart-summary">
                        <div class="summary-box">
                            <h2 class="summary-title">Order Summary</h2>

                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>$<?= number_format((float) $cartTotal, 2) ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span>Free</span>
                            </div>
                            <div class="summary-row">
                                <span>Tax</span>
                                <span>Calculated at checkout</span>
                            </div>

                            <div class="summary-divider"></div>

                            <div class="summary-row summary-row--total">
                                <span>Total</span>
                                <span>$<?= number_format((float) $cartTotal, 2) ?></span>
                            </div>

                            <!-- Checkout Form -->
                            <form method="POST" action="cart.php" class="checkout-form">
                                <input type="hidden" name="action" value="checkout">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn--primary btn--lg btn--full">
                                    Proceed to Checkout
                                </button>
                            </form>

                            <!-- Clear Cart Form -->
                            <form method="POST" action="cart.php" class="clear-cart-form" onsubmit="return confirm('Clear entire cart?')">
                                <input type="hidden" name="action" value="clear_cart">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn btn--outline btn--lg btn--full">
                                    Clear Cart
                                </button>
                            </form>

                            <a href="index.php" class="btn btn--ghost btn--lg btn--full">
                                Continue Shopping
                            </a>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Footer -->
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
// Auto-dismiss flash alert
const flashAlert = document.getElementById('flash-alert');
if (flashAlert) {
    setTimeout(() => {
        flashAlert.style.opacity = '0';
        flashAlert.style.transition = 'opacity 0.5s';
        setTimeout(() => flashAlert.remove(), 500);
    }, 5000);
}

// Handle quantity buttons
function validateQuantity(form) {
    const quantityInput = form.querySelector('.qty-input');
    const adjust = form.querySelector('button[type="submit"]:active');
    
    if (adjust) {
        const currentQty = parseInt(quantityInput.value) || 1;
        const maxQty = parseInt(quantityInput.max) || 999;

        if (adjust.value === 'minus' && currentQty > 1) {
            quantityInput.value = currentQty - 1;
        } else if (adjust.value === 'plus' && currentQty < maxQty) {
            quantityInput.value = currentQty + 1;
        } else if (adjust.value === 'plus') {
            alert('Maximum available quantity reached.');
            return false;
        }
    }
    return true;
}
</script>
</body>
</html>
