<?php
// profile.php — Customer Profile and Shopping History
require_once 'db.php';

// Enforce authentication
requireLogin();

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Fetch latest user details
$stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    // Session user doesn't exist in DB
    session_destroy();
    redirect('login.php');
}

// Fetch user orders
$stmt = $pdo->prepare(
    "SELECT id, total, created_at, status 
     FROM orders 
     WHERE user_id = ? 
     ORDER BY created_at DESC"
);
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

// Fetch items for user orders
$order_items_map = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT oi.order_id, p.title as product_title, oi.quantity, oi.price_at_purchase, p.currency
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id IN ($placeholders)"
    );
    $stmt->execute($order_ids);
    $items = $stmt->fetchAll();
    foreach ($items as $item) {
        $order_items_map[$item['order_id']][] = $item;
    }
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
    <title>My Profile — A-Commerce</title>
    <meta name="description" content="Manage your profile settings and view your order history." />
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
                <h1 class="section-title">My Account</h1>
                <p class="section-subtitle">Manage your personal information and track your shopping history</p>
            </div>

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                    <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="admin-tabs">
                <button class="admin-tab-btn admin-tab-btn--active" data-tab="profile-info">
                    👤 Profile Details
                </button>
                <button class="admin-tab-btn" data-tab="order-history">
                    🛍️ Shopping History (<?= count($orders) ?>)
                </button>
            </div>

            <!-- Tab: Profile Details -->
            <div class="admin-tab-content admin-tab-content--active" id="profile-info-tab">
                <div class="auth-card" style="padding: var(--space-xl); width: 100%; max-width: 600px; margin: 0 auto;">
                    <h2 class="admin-subsection-title" style="text-align: center; margin-bottom: var(--space-lg);">Update Profile Information</h2>
                    <form method="POST" action="profile-handler.php" class="admin-form">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" id="name" name="name" required class="form-input" 
                                   value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" style="padding-left: var(--space-md);">
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" required class="form-input" 
                                   value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" style="padding-left: var(--space-md);">
                        </div>

                        <h3 style="margin-top: var(--space-xl); margin-bottom: var(--space-md); border-top: 1px solid var(--clr-border); padding-top: var(--space-lg); font-size: var(--fs-md); color: var(--clr-text-main);">
                            Change Password (Leave blank to keep current)
                        </h3>

                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-input" style="padding-left: var(--space-md);">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-input" style="padding-left: var(--space-md);">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" style="padding-left: var(--space-md);">
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary btn--lg btn--full" style="margin-top: var(--space-lg);">
                            💾 Save Profile Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tab: Shopping History -->
            <div class="admin-tab-content" id="order-history-tab">
                <h2 class="admin-subsection-title">My Order History</h2>
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">🛍️</span>
                        <h3>No orders placed yet</h3>
                        <p>When you checkout items from your cart, they will appear here.</p>
                        <a href="index.php" class="btn btn--primary btn--lg" style="margin-top: var(--space-md);">
                            Go Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Items Ordered</th>
                                    <th>Total Price</th>
                                    <th>Status</th>
                                    <th>Placed Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                        $statusClass = 'role-badge--customer'; // pending (default theme color)
                                        if ($order['status'] === 'confirmed') {
                                            $statusClass = 'role-badge--manager'; // confirmed (green theme)
                                        } elseif ($order['status'] === 'cancelled') {
                                            $statusClass = 'role-badge--admin'; // cancelled (red/danger theme)
                                        }
                                    ?>
                                    <tr>
                                        <td>#<?= (int) $order['id'] ?></td>
                                        <td>
                                            <ul class="order-items-list" style="margin: 0; padding-left: 1.2rem; text-align: left;">
                                                <?php if (isset($order_items_map[$order['id']])): ?>
                                                    <?php foreach ($order_items_map[$order['id']] as $item): ?>
                                                        <li>
                                                            <?= htmlspecialchars($item['product_title'] ?? 'Deleted Product', ENT_QUOTES, 'UTF-8') ?> 
                                                            <strong>x<?= (int) $item['quantity'] ?></strong> 
                                                            <span style="color: var(--clr-text-muted);">(<?= htmlspecialchars($item['currency'] ?? 'Ksh', ENT_QUOTES, 'UTF-8') ?> <?= number_format((float) $item['price_at_purchase'], 2) ?> each)</span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <li>No items recorded</li>
                                                <?php endif; ?>
                                            </ul>
                                        </td>
                                        <td>
                                            <?php 
                                                $orderCurrency = 'Ksh';
                                                if (isset($order_items_map[$order['id']]) && !empty($order_items_map[$order['id']])) {
                                                    $orderCurrency = $order_items_map[$order['id']][0]['currency'] ?? 'Ksh';
                                                }
                                            ?>
                                            <strong style="color: var(--clr-primary);"><?= htmlspecialchars($orderCurrency, ENT_QUOTES, 'UTF-8') ?> <?= number_format((float) $order['total'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="role-badge <?= $statusClass ?>">
                                                <?= ucfirst(htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8')) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></td>
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
