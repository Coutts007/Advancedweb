<?php
// edit-user.php — Admin Edit User Page
require_once 'db.php';

// Enforce admin access
requireAdmin();

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id || $user_id < 1) {
    setFlash('error', 'Invalid user ID.');
    redirect('admin-dashboard.php');
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'User not found.');
    redirect('admin-dashboard.php');
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
    <title>Edit User — A-Commerce</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">

<?php include 'navbar.php'; ?>

<main id="main-content" class="admin-main">
    <section class="admin-section">
        <div class="container" style="max-width: 600px;">
            <div class="section-header">
                <a href="admin-dashboard.php" class="btn btn--outline btn--sm" style="margin-bottom: var(--space-md); display: inline-flex; align-items: center; gap: var(--space-xs);">
                    ← Back to Dashboard
                </a>
                <h1 class="section-title">Edit User</h1>
                <p class="section-subtitle">Modify profile and role for "<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>"</p>
            </div>

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="alert alert--<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert" id="flash-alert">
                    <span class="alert-icon"><?= $flash['type'] === 'success' ? '✅' : '❌' ?></span>
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="auth-card" style="padding: var(--space-xl); width: 100%; max-width: 100%;">
                <form method="POST" action="admin-handler.php" class="admin-form">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
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

                    <div class="form-group">
                        <label for="role" class="form-label">System Role *</label>
                        <select id="role" name="role" required class="form-input" 
                                style="padding-left: var(--space-md); padding-right: var(--space-md); background-color: var(--clr-bg-input);"
                                <?= (int)$user['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Store Manager</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                        <?php if ((int)$user['id'] === (int)$_SESSION['user_id']): ?>
                            <input type="hidden" name="role" value="admin">
                            <span style="font-size: var(--fs-xs); color: var(--clr-text-muted); font-style: italic; display: block; margin-top: var(--space-xs);">You cannot change your own role.</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Reset Password</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Leave blank to keep current password" style="padding-left: var(--space-md);">
                    </div>

                    <div style="display: flex; gap: var(--space-md); margin-top: var(--space-xl);">
                        <button type="submit" class="btn btn--primary btn--lg" style="flex: 1; justify-content: center;">
                            💾 Save Changes
                        </button>
                        <a href="admin-dashboard.php" class="btn btn--ghost btn--lg" style="flex: 1; justify-content: center; align-items: center; display: flex;">
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
