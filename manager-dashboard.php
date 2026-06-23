<?php
// manager-dashboard.php — Manager dashboard for product management and order checkouts
require_once 'db.php';

// Enforce manager access
requireManager();

$pdo = getPDO();

// ── Fetch statistics ──────────────────────────────────────────
// Total products count
$stmt = $pdo->query('SELECT COUNT(*) FROM products');
$total_products = $stmt->fetchColumn();

// Low stock items count
$stmt = $pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 5 AND stock > 0');
$low_stock_count = $stmt->fetchColumn();

// Pending checkouts count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
$stmt->execute(['pending']);
$pending_checkouts_count = $stmt->fetchColumn();

// ── Fetch Pending Checkouts ───────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT o.id, o.user_id, u.name as user_name, u.email as user_email, o.total, o.created_at, o.status
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     WHERE o.status = ?
     ORDER BY o.created_at DESC"
);
$stmt->execute(['pending']);
$pending_orders = $stmt->fetchAll();

// Fetch items for pending checkouts
$order_items_map = [];
if (!empty($pending_orders)) {
    $pending_ids = array_column($pending_orders, 'id');
    $placeholders = implode(',', array_fill(0, count($pending_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT oi.order_id, p.title as product_title, oi.quantity, oi.price_at_purchase
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id IN ($placeholders)"
    );
    $stmt->execute($pending_ids);
    $items = $stmt->fetchAll();
    foreach ($items as $item) {
        $order_items_map[$item['order_id']][] = $item;
    }
}

// ── Fetch All Products for Management ─────────────────────────
$stmt = $pdo->query('SELECT id, title, description, price, stock, image_url, created_at FROM products ORDER BY id DESC');
$all_products = $stmt->fetchAll();

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
    <title>Manager Dashboard — A-Commerce</title>
    <meta name="description" content="Store Manager dashboard for product and checkout management." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">

<?php include 'navbar.php'; ?>

<main id="main-content" class="admin-main">
    <section class="admin-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">Manager Dashboard</h1>
                <p class="section-subtitle">Perform product CRUD operations and confirm customer checkouts</p>
            </div>

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                    <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Stats -->
            <div class="admin-stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Pending Checkouts</div>
                    <div class="stat-value"><?= (int) $pending_checkouts_count ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value"><?= (int) $total_products ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value"><?= (int) $low_stock_count ?></div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="admin-tabs">
                <button class="admin-tab-btn admin-tab-btn--active" data-tab="checkouts">
                    🛎️ Pending Checkouts (<?= count($pending_orders) ?>)
                </button>
                <button class="admin-tab-btn" data-tab="products">
                    📦 Manage Products
                </button>
                <button class="admin-tab-btn" data-tab="add-product">
                    ➕ Add New Product
                </button>
            </div>

            <!-- Tab: Pending Checkouts -->
            <div class="admin-tab-content admin-tab-content--active" id="checkouts-tab">
                <h2 class="admin-subsection-title">Pending Customer Checkouts</h2>
                <?php if (empty($pending_orders)): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">🛎️</span>
                        <h3>No pending checkouts</h3>
                        <p>There are no customer checkouts waiting for confirmation.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer Details</th>
                                    <th>Items Ordered</th>
                                    <th>Total Value</th>
                                    <th>Placed Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_orders as $order): ?>
                                    <tr>
                                        <td>#<?= (int) $order['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($order['user_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></strong><br>
                                            <span style="font-size: var(--fs-xs); color: var(--clr-text-muted);"><?= htmlspecialchars($order['user_email'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td>
                                            <ul class="order-items-list" style="margin: 0; padding-left: 1.2rem; text-align: left;">
                                                <?php if (isset($order_items_map[$order['id']])): ?>
                                                    <?php foreach ($order_items_map[$order['id']] as $item): ?>
                                                        <li>
                                                            <?= htmlspecialchars($item['product_title'] ?? 'Deleted Product', ENT_QUOTES, 'UTF-8') ?> 
                                                            <strong>x<?= (int) $item['quantity'] ?></strong> 
                                                            <span style="color: var(--clr-text-muted);">($<?= number_format((float) $item['price_at_purchase'], 2) ?> each)</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <li>No items recorded</li>
                                                <?php endif; ?>
                                            </ul>
                                        </td>
                                        <td><strong style="color: var(--clr-primary);">$<?= number_format((float) $order['total'], 2) ?></strong></td>
                                        <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <div style="display: flex; gap: var(--space-xs);">
                                                <form method="POST" action="manager-handler.php" style="display: inline;" onsubmit="return confirm('Confirm this checkout? This will decrement product stock.');">
                                                    <input type="hidden" name="action" value="confirm_checkout">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn--primary btn--sm" style="background-color: var(--clr-success); border-color: var(--clr-success);">Confirm</button>
                                                </form>
                                                <form method="POST" action="manager-handler.php" style="display: inline;" onsubmit="return confirm('Cancel this customer checkout?');">
                                                    <input type="hidden" name="action" value="cancel_checkout">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn--danger btn--sm">Cancel</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Product Management -->
            <div class="admin-tab-content" id="products-tab">
                <h2 class="admin-subsection-title">Manage Products</h2>
                <?php if (empty($all_products)): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">📦</span>
                        <h3>No products yet</h3>
                        <p>Add your first product to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_products as $product): ?>
                                    <?php
                                        $status = 'In Stock';
                                        $status_class = 'product-status--in-stock';
                                        if ($product['stock'] === 0) {
                                            $status = 'Out of Stock';
                                            $status_class = 'product-status--out-of-stock';
                                        } elseif ($product['stock'] <= 5) {
                                            $status = 'Low Stock';
                                            $status_class = 'product-status--low-stock';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="product-cell">
                                                <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="product-thumb" onerror="this.src='https://picsum.photos/seed/fallback/50/50'; this.onerror=null;">
                                                <span><strong><?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                            </div>
                                        </td>
                                        <td>$<?= number_format((float) $product['price'], 2) ?></td>
                                        <td>
                                            <form class="inline-form" method="POST" action="manager-handler.php" onsubmit="return validateStockForm(this)">
                                                <input type="hidden" name="action" value="update_stock">
                                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="number" name="stock" value="<?= (int) $product['stock'] ?>" min="0" class="stock-input" placeholder="Stock">
                                                <button type="submit" class="btn-inline">Update</button>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="product-status <?= $status_class ?>">
                                                <?= $status ?> (<?= (int) $product['stock'] ?>)
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: var(--space-xs);">
                                                <a href="edit-product.php?id=<?= (int) $product['id'] ?>" class="btn btn--outline btn--sm">Edit</a>
                                                <form class="inline-form" method="POST" action="manager-handler.php" onsubmit="return confirm('Delete this product? This action cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Add Product -->
            <div class="admin-tab-content" id="add-product-tab">
                <h2 class="admin-subsection-title">Add New Product</h2>
                <form method="POST" action="manager-handler.php" class="admin-form">
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="title" class="form-label">Product Title *</label>
                        <input type="text" id="title" name="title" required class="form-input" placeholder="e.g., Premium Wireless Headphones">
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Description *</label>
                        <textarea id="description" name="description" required class="form-textarea" rows="4" placeholder="Detailed product description..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="price" class="form-label">Price (USD) *</label>
                            <input type="number" id="price" name="price" required step="0.01" min="0" class="form-input" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="stock" class="form-label">Initial Stock *</label>
                            <input type="number" id="stock" name="stock" required min="0" class="form-input" placeholder="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="image_url" class="form-label">Image URL *</label>
                        <input type="url" id="image_url" name="image_url" required class="form-input" placeholder="https://example.com/image.jpg">
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg">
                        ➕ Add Product
                    </button>
                </form>
            </div>

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
// Tab switching
document.querySelectorAll('.admin-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabName = btn.dataset.tab;
        
        // Hide all tabs
        document.querySelectorAll('.admin-tab-content').forEach(tab => {
            tab.classList.remove('admin-tab-content--active');
        });
        
        // Remove active state from all buttons
        document.querySelectorAll('.admin-tab-btn').forEach(b => {
            b.classList.remove('admin-tab-btn--active');
        });
        
        // Show selected tab
        document.getElementById(tabName + '-tab').classList.add('admin-tab-content--active');
        btn.classList.add('admin-tab-btn--active');
    });
});

// Auto-dismiss flash alert
const flashAlert = document.getElementById('flash-alert');
if (flashAlert) {
    setTimeout(() => {
        flashAlert.style.opacity = '0';
        flashAlert.style.transition = 'opacity 0.5s';
        setTimeout(() => flashAlert.remove(), 500);
    }, 5000);
}

// Validate stock form
function validateStockForm(form) {
    const input = form.querySelector('[name="stock"]');
    const value = parseInt(input.value);
    if (isNaN(value) || value < 0) {
        alert('Stock must be a non-negative number.');
        return false;
    }
    return true;
}
</script>
</body>
</html>
