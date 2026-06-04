<?php
// admin-dashboard.php — Admin dashboard for product management and sales monitoring
require_once 'db.php';

// Enforce admin access
requireAdmin();

// ── Fetch dashboard statistics ────────────────────────────────
$pdo = getPDO();

// Total revenue and order count
$stmt = $pdo->query('SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue FROM orders');
$stats = $stmt->fetch();

// Recent orders (last 10)
$stmt = $pdo->query(
    'SELECT o.id, o.user_id, u.name as user_name, o.total, o.created_at, COUNT(oi.id) as item_count
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     LEFT JOIN order_items oi ON o.id = oi.order_id
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 10'
);
$recent_orders = $stmt->fetchAll();

// Product stats (top sellers, low stock)
$stmt = $pdo->query(
    'SELECT p.id, p.title, p.price, p.stock, COUNT(oi.id) as total_sold, SUM(oi.quantity) as quantity_sold
     FROM products p
     LEFT JOIN order_items oi ON p.id = oi.product_id
     GROUP BY p.id
     ORDER BY quantity_sold DESC'
);
$products_stats = $stmt->fetchAll();

// All products for management
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
    <title>Admin Dashboard — A-Commerce</title>
    <meta name="description" content="Admin dashboard for product management and sales monitoring." />
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
                <h1 class="section-title">Admin Dashboard</h1>
                <p class="section-subtitle">Manage products and monitor sales</p>
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
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?= (int) $stats['order_count'] ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">$<?= number_format((float) $stats['total_revenue'], 2) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value"><?= count($all_products) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Low Stock Items</div>
                    <div class="stat-value"><?= count(array_filter($all_products, fn($p) => $p['stock'] <= 5 && $p['stock'] > 0)) ?></div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="admin-tabs">
                <button class="admin-tab-btn admin-tab-btn--active" data-tab="sales">
                    📊 Sales & Orders
                </button>
                <button class="admin-tab-btn" data-tab="products">
                    📦 Products
                </button>
                <button class="admin-tab-btn" data-tab="add-product">
                    ➕ Add New Product
                </button>
            </div>

            <!-- Tab: Sales & Orders -->
            <div class="admin-tab-content admin-tab-content--active" id="sales-tab">
                <h2 class="admin-subsection-title">Recent Orders</h2>
                <?php if (empty($recent_orders)): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">📭</span>
                        <h3>No orders yet</h3>
                        <p>Orders will appear here once customers make purchases.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?= (int) $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['user_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $order['item_count'] ?></td>
                                        <td><strong>$<?= number_format((float) $order['total'], 2) ?></strong></td>
                                        <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h2 class="admin-subsection-title" style="margin-top: 3rem;">Top Products</h2>
                <?php $top_products = array_slice($products_stats, 0, 5); ?>
                <?php if (empty($top_products)): ?>
                    <p>No sales data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>$<?= number_format((float) $product['price'], 2) ?></td>
                                        <td><?= (int) ($product['quantity_sold'] ?? 0) ?></td>
                                        <td><?= $product['quantity_sold'] ? '$' . number_format((float) $product['price'] * (int) $product['quantity_sold'], 2) : '$0.00' ?></td>
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
                                                <span><?= htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </td>
                                        <td>$<?= number_format((float) $product['price'], 2) ?></td>
                                        <td>
                                            <form class="inline-form" method="POST" action="admin-handler.php" onsubmit="return validateStockForm(this)">
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
                                            <form class="inline-form" method="POST" action="admin-handler.php" onsubmit="return confirm('Delete this product? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                                            </form>
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
                <form method="POST" action="admin-handler.php" class="admin-form">
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
