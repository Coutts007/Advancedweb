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
        $success = false;
        $message = 'Item not found in cart.';
        if ($productId && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
            $success = true;
            $message = 'Item removed from cart.';
            setFlash('success', $message);
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $cartTotal = 0;
            $cartCount = 0;
            $pdo = getPDO();
            if (!empty($_SESSION['cart'])) {
                $productIds = array_keys($_SESSION['cart']);
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $products = $stmt->fetchAll();
                foreach ($products as $p) {
                    $qty = $_SESSION['cart'][$p['id']];
                    $cartTotal += $p['price'] * $qty;
                    $cartCount += $qty;
                }
            }
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'cart_total' => number_format((float) $cartTotal, 2),
                'cart_count' => $cartCount
            ]);
            exit;
        }
        redirect('cart.php');
    }

    if ($action === 'update_quantity') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $adjust = $_POST['adjust'] ?? null;
        $success = false;
        $message = '';
        $removed = false;
        $itemPrice = 0;
        $itemTotal = 0;
        $itemCurrency = 'Ksh';

        if ($productId && $quantity !== false) {
            // Server-side fallback adjustment if JS didn't modify the input
            if ($adjust === 'minus') {
                $quantity = $quantity - 1;
            } elseif ($adjust === 'plus') {
                $quantity = $quantity + 1;
            }

            if ($quantity <= 0) {
                unset($_SESSION['cart'][$productId]);
                $success = true;
                $message = 'Item removed from cart.';
                $removed = true;
                setFlash('success', 'Item removed from cart.');
            } else {
                // Verify product exists and has stock
                $pdo = getPDO();
                $stmt = $pdo->prepare('SELECT stock, price, currency FROM products WHERE id = ? LIMIT 1');
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if ($product && $quantity <= $product['stock']) {
                    $_SESSION['cart'][$productId] = $quantity;
                    $success = true;
                    $message = 'Cart updated.';
                    $removed = false;
                    $itemPrice = $product['price'];
                    $itemTotal = $product['price'] * $quantity;
                    $itemCurrency = $product['currency'];
                    setFlash('success', 'Cart updated.');
                } else {
                    $success = false;
                    $message = 'Invalid quantity or insufficient stock.';
                    $removed = false;
                    setFlash('warning', 'Invalid quantity or insufficient stock.');
                }
            }
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // Recalculate cart total and count
            $cartTotal = 0;
            $cartCount = 0;
            $cartCurrency = 'Ksh';
            $pdo = getPDO();
            if (!empty($_SESSION['cart'])) {
                $productIds = array_keys($_SESSION['cart']);
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $pdo->prepare("SELECT id, price, currency FROM products WHERE id IN ($placeholders)");
                $stmt->execute($productIds);
                $products = $stmt->fetchAll();
                if (!empty($products)) {
                    $cartCurrency = $products[0]['currency'];
                }
                foreach ($products as $p) {
                    $qty = $_SESSION['cart'][$p['id']];
                    $cartTotal += $p['price'] * $qty;
                    $cartCount += $qty;
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'removed' => $removed,
                'item_price_formatted' => htmlspecialchars($itemCurrency, ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) $itemPrice, 2),
                'item_total_formatted' => htmlspecialchars($itemCurrency, ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) $itemTotal, 2),
                'cart_total' => number_format((float) $cartTotal, 2),
                'cart_count' => $cartCount,
                'cart_currency' => htmlspecialchars($cartCurrency, ENT_QUOTES, 'UTF-8')
            ]);
            exit;
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
            redirect('cart.php');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Fetch cart products to verify prices and stock
            $productIds = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("SELECT id, title, price, stock FROM products WHERE id IN ($placeholders) FOR UPDATE");
            $stmt->execute($productIds);
            $products = $stmt->fetchAll();

            $productsMap = [];
            foreach ($products as $p) {
                $productsMap[$p['id']] = $p;
            }

            $orderItemsToInsert = [];
            $orderTotal = 0;

            foreach ($_SESSION['cart'] as $productId => $qty) {
                if (!isset($productsMap[$productId])) {
                    throw new Exception("Product not found.");
                }

                $p = $productsMap[$productId];
                if ($p['stock'] < $qty) {
                    throw new Exception("Insufficient stock for product: " . $p['title']);
                }

                $itemTotal = $p['price'] * $qty;
                $orderTotal += $itemTotal;

                $orderItemsToInsert[] = [
                    'product_id' => $p['id'],
                    'quantity' => $qty,
                    'price_at_purchase' => $p['price']
                ];
            }

            // Insert order
            $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, total, status) VALUES (?, ?, 'pending')");
            $stmtOrder->execute([$_SESSION['user_id'], $orderTotal]);
            $orderId = $pdo->lastInsertId();

            // Insert order items
            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
            foreach ($orderItemsToInsert as $item) {
                $stmtItem->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price_at_purchase']
                ]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            setFlash('success', 'Order placed successfully! It is pending confirmation from a manager.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout error: ' . $e->getMessage());
            setFlash('error', 'Checkout failed: ' . $e->getMessage());
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
    
    $stmt = $pdo->prepare("SELECT id, title, price, image_url, stock, currency FROM products WHERE id IN ($placeholders)");
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
            'currency' => $product['currency'],
        ];
    }
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$cartCurrency = !empty($cartItems) ? $cartItems[0]['currency'] : 'Ksh';
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

<!-- Toast notification -->
<div class="toast" id="cart-toast" role="status" aria-live="polite" aria-atomic="true">
    <span class="toast-icon">🛒</span>
    <span class="toast-text" id="toast-text"></span>
</div>

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
                                            <span class="cart-item-price"><?= htmlspecialchars($item['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float) $item['price'], 2) ?></span>
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
                                            <span class="cart-item-total-amount"><?= htmlspecialchars($item['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float) $item['itemTotal'], 2) ?></span>
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
                                <span><?= htmlspecialchars($cartCurrency, ENT_QUOTES, 'UTF-8') ?> <?= number_format((float) $cartTotal, 2) ?></span>
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
                                <span><?= htmlspecialchars($cartCurrency, ENT_QUOTES, 'UTF-8') ?> <?= number_format((float) $cartTotal, 2) ?></span>
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

// ── Toast notification ────────────────────────────────────────
let toastTimer;
function showToast(message, type = 'success') {
    const toast    = document.getElementById('cart-toast');
    const toastTxt = document.getElementById('toast-text');
    if (!toast || !toastTxt) return;

    toastTxt.textContent    = message;
    toast.dataset.type      = type;
    toast.querySelector('.toast-icon').textContent = type === 'error' ? '❌' : '🛒';

    toast.classList.add('toast--visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('toast--visible'), 3000);
}

// ── Update Cart Badge counter in navbar ───────────────────────
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

// Handle plus and minus buttons via AJAX
document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const form = btn.form;
        const input = form.querySelector('.qty-input');
        const maxStock = parseInt(input.max) || 999;
        let val = parseInt(input.value) || 1;
        const productId = form.querySelector('[name="product_id"]').value;
        const csrfToken = form.querySelector('[name="csrf_token"]').value;

        let newVal = val;
        if (btn.classList.contains('qty-btn--minus')) {
            if (val <= 1) {
                if (!confirm('Remove this item from cart?')) {
                    return;
                }
                newVal = 0;
            } else {
                newVal--;
            }
        } else if (btn.classList.contains('qty-btn--plus')) {
            if (val >= maxStock) {
                alert('Maximum available quantity reached.');
                return;
            }
            newVal++;
        }

        try {
            const response = await fetch('cart.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'update_quantity',
                    product_id: productId,
                    quantity: newVal,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (data.success) {
                if (data.removed) {
                    // Remove item card from DOM
                    const card = btn.closest('.cart-item');
                    if (card) {
                        card.style.opacity = '0';
                        card.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => {
                            card.remove();
                            // If cart is now empty, reload to show empty state
                            if (document.querySelectorAll('.cart-item').length === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    }
                    showToast('Item removed from cart.');
                } else {
                    // Update value and totals in DOM
                    input.value = newVal;
                    const card = btn.closest('.cart-item');
                    if (card) {
                        const totalEl = card.querySelector('.cart-item-total-amount');
                        if (totalEl) totalEl.textContent = data.item_total_formatted;
                    }
                    showToast('Cart updated.');
                }

                // Update order summary subtotal & total
                const subtotalEl = document.querySelector('.summary-row:nth-child(2) span:last-child');
                const totalEl = document.querySelector('.summary-row--total span:last-child');
                if (subtotalEl) subtotalEl.textContent = data.cart_currency + ' ' + data.cart_total;
                if (totalEl) totalEl.textContent = data.cart_currency + ' ' + data.cart_total;

                updateCartBadge(data.cart_count);
            } else {
                showToast(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Failed to update quantity.', 'error');
        }
    });
});

// Handle item removal via AJAX
document.querySelectorAll('.cart-item-remove-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!confirm('Remove this item from cart?')) {
            return;
        }

        const productId = form.querySelector('[name="product_id"]').value;
        const csrfToken = form.querySelector('[name="csrf_token"]').value;

        try {
            const response = await fetch('cart.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'remove_item',
                    product_id: productId,
                    csrf_token: csrfToken
                })
            });

            const data = await response.json();

            if (data.success) {
                const card = form.closest('.cart-item');
                if (card) {
                    card.style.opacity = '0';
                    card.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        card.remove();
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            window.location.reload();
                        }
                    }, 300);
                }
                showToast('Item removed from cart.');

                // Update order summary subtotal & total
                const subtotalEl = document.querySelector('.summary-row:nth-child(2) span:last-child');
                const totalEl = document.querySelector('.summary-row--total span:last-child');
                const cartCurrency = document.querySelector('.cart-item-price')?.textContent.split(' ')[0] || 'Ksh';
                if (subtotalEl) subtotalEl.textContent = cartCurrency + ' ' + data.cart_total;
                if (totalEl) totalEl.textContent = cartCurrency + ' ' + data.cart_total;

                updateCartBadge(data.cart_count);
            } else {
                showToast(data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast('Failed to remove item.', 'error');
        }
    });
});
</script>
</body>
</html>
