<?php
// edit-product.php — Store Manager Edit Product Page
require_once 'db.php';

// Enforce manager access
requireManager();

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id || $product_id < 1) {
    setFlash('error', 'Invalid product ID.');
    redirect('manager-dashboard.php');
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT id, title, description, price, stock, image_url FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Product not found.');
    redirect('manager-dashboard.php');
}

// Generate CSRF token
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
    <title>Edit Product — A-Commerce</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">

<?php include 'navbar.php'; ?>

<main id="main-content" class="admin-main">
    <section class="admin-section">
        <div class="container" style="max-width: 800px;">
            <div class="section-header">
                <a href="manager-dashboard.php" class="btn btn--outline btn--sm" style="margin-bottom: var(--space-md); display: inline-flex; align-items: center; gap: var(--space-xs);">
                    ← Back to Dashboard
                </a>
                <h1 class="section-title">Edit Product</h1>
                <p class="section-subtitle">Modify details for "<?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?>"</p>
            </div>

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                    <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="auth-card" style="padding: var(--space-xl); width: 100%; max-width: 100%;">
                <form method="POST" action="manager-handler.php" class="admin-form">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="title" class="form-label">Product Title *</label>
                        <input type="text" id="title" name="title" required class="form-input" 
                               value="<?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?>" style="padding-left: var(--space-md);">
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" required class="form-textarea" rows="5"><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="price" class="form-label">Price (USD) *</label>
                            <input type="number" id="price" name="price" required step="0.01" min="0" class="form-input" 
                                   value="<?= htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8') ?>" style="padding-left: var(--space-md);">
                        </div>

                        <div class="form-group">
                            <label for="stock" class="form-label">Stock *</label>
                            <input type="number" id="stock" name="stock" required min="0" class="form-input" 
                                   value="<?= (int) $product['stock'] ?>" style="padding-left: var(--space-md);">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="image_url" class="form-label">Image URL *</label>
                        <input type="url" id="image_url" name="image_url" required class="form-input" 
                               value="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>" style="padding-left: var(--space-md);">
                    </div>

                    <div style="display: flex; gap: var(--space-md); margin-top: var(--space-xl);">
                        <button type="submit" class="btn btn--primary btn--lg" style="flex: 1; justify-content: center;">
                            💾 Save Changes
                        </button>
                        <a href="manager-dashboard.php" class="btn btn--ghost btn--lg" style="flex: 1; justify-content: center; align-items: center; display: flex;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-brand">
            <span class="brand-icon">🛍️</span>
            <span class="brand-name">A-Commerce</span>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> A-Commerce. All rights reserved.</p>
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
</script>
</body>
</html>
