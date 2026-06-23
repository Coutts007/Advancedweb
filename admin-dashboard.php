<?php
// admin-dashboard.php — Admin dashboard for user management and sales monitoring
require_once 'db.php';

// Enforce admin access
requireAdmin();

$pdo = getPDO();

// ── Fetch statistics ──────────────────────────────────────────
// Total revenue and order count
$stmt = $pdo->query('SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue FROM orders');
$stats = $stmt->fetch();

// Total users count
$stmt = $pdo->query('SELECT COUNT(*) FROM users');
$total_users = $stmt->fetchColumn();

// Total products count
$stmt = $pdo->query('SELECT COUNT(*) FROM products');
$total_products = $stmt->fetchColumn();

// ── Fetch Recent Orders (last 10) ─────────────────────────────
$stmt = $pdo->query(
    'SELECT o.id, o.user_id, u.name as user_name, o.total, o.created_at, o.status, COUNT(oi.id) as item_count
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     LEFT JOIN order_items oi ON o.id = oi.order_id
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 10'
);
$recent_orders = $stmt->fetchAll();

// Product stats (top sellers)
$stmt = $pdo->query(
    'SELECT p.id, p.title, p.price, p.stock, COUNT(oi.id) as total_sold, SUM(oi.quantity) as quantity_sold
     FROM products p
     LEFT JOIN order_items oi ON p.id = oi.product_id
     GROUP BY p.id
     ORDER BY quantity_sold DESC'
);
$products_stats = $stmt->fetchAll();

// ── Fetch All Users for Management ─────────────────────────────
$stmt = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC');
$all_users = $stmt->fetchAll();

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
    <meta name="description" content="Admin dashboard for user management and sales monitoring." />
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
                <p class="section-subtitle">Manage users and monitor sales statistics</p>
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
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= (int) $total_users ?></div>
                </div>
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
                    <div class="stat-value"><?= (int) $total_products ?></div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="admin-tabs">
                <button class="admin-tab-btn admin-tab-btn--active" data-tab="users">
                    👥 Manage Users (<?= count($all_users) ?>)
                </button>
                <button class="admin-tab-btn" data-tab="add-user">
                    ➕ Add New User
                </button>
                <button class="admin-tab-btn" data-tab="sales">
                    📊 Sales & Orders
                </button>
            </div>

            <!-- Tab: Manage Users -->
            <div class="admin-tab-content admin-tab-content--active" id="users-tab">
                <h2 class="admin-subsection-title">Platform Users</h2>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_users as $user): ?>
                                <?php
                                    $role_badge = 'role-badge--customer';
                                    if ($user['role'] === 'admin') {
                                        $role_badge = 'role-badge--admin';
                                    } elseif ($user['role'] === 'manager') {
                                        $role_badge = 'role-badge--manager';
                                    }
                                ?>
                                <tr>
                                    <td>#<?= (int) $user['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                    <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="role-badge <?= $role_badge ?>">
                                            <?= ucfirst(htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div style="display: flex; gap: var(--space-xs);">
                                            <a href="edit-user.php?id=<?= (int) $user['id'] ?>" class="btn btn--outline btn--sm">Edit</a>
                                            
                                            <?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                                                <form method="POST" action="admin-handler.php" style="display: inline;" onsubmit="return confirm('Delete this user? This will remove all their data.');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="submit" class="btn btn--danger btn--sm">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: var(--fs-xs); color: var(--clr-text-muted); font-style: italic; align-self: center;">Self (Protected)</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Add User -->
            <div class="admin-tab-content" id="add-user-tab">
                <h2 class="admin-subsection-title">Add New User</h2>
                <form method="POST" action="admin-handler.php" class="admin-form" style="max-width: 600px;">
                    <input type="hidden" name="action" value="add_user">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" id="name" name="name" required class="form-input" placeholder="e.g. Jane Smith" style="padding-left: var(--space-md);">
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" required class="form-input" placeholder="e.g. jane@example.com" style="padding-left: var(--space-md);">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="role" class="form-label">System Role *</label>
                            <select id="role" name="role" required class="form-input" style="padding-left: var(--space-md); padding-right: var(--space-md); background-color: var(--clr-bg-input);">
                                <option value="customer">Customer</option>
                                <option value="manager">Store Manager</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password (Min 8 chars) *</label>
                            <input type="password" id="password" name="password" required class="form-input" placeholder="••••••••" style="padding-left: var(--space-md);">
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primary btn--lg">
                        ➕ Add User
                    </button>
                </form>
            </div>

            <!-- Tab: Sales & Orders -->
            <div class="admin-tab-content" id="sales-tab">
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
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <?php
                                        $status_class = 'product-status--in-stock';
                                        if ($order['status'] === 'pending') {
                                            $status_class = 'product-status--low-stock';
                                        } elseif ($order['status'] === 'cancelled') {
                                            $status_class = 'product-status--out-of-stock';
                                        }
                                    ?>
                                    <tr>
                                        <td>#<?= (int) $order['id'] ?></td>
                                        <td><?= htmlspecialchars($order['user_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $order['item_count'] ?></td>
                                        <td><strong>$<?= number_format((float) $order['total'], 2) ?></strong></td>
                                        <td>
                                            <span class="product-status <?= $status_class ?>">
                                                <?= ucfirst(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?>
                                            </span>
                                        </td>
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
</script>
</body>
</html>
