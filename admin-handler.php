<?php
// admin-handler.php — Admin operations processor for users and products
require_once 'db.php';

// Enforce authentication and admin role
requireAdmin();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    redirect('admin-dashboard.php');
}

// ── CSRF validation ───────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    setFlash('error', 'Invalid form submission. Please try again.');
    redirect('admin-dashboard.php');
}

$action = $_POST['action'] ?? null;
$pdo = getPDO();

// ── ADD USER (Admin Privilege) ────────────────────────────────
if ($action === 'add_user') {
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email = trim(htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'));
    $role = trim($_POST['role'] ?? 'customer');
    $password = $_POST['password'] ?? '';

    $errors = [];
    if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors[] = 'Name must be between 2 and 120 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!in_array($role, ['customer', 'manager', 'admin'], true)) {
        $errors[] = 'Invalid role selection.';
    }

    if (!empty($errors)) {
        setFlash('error', implode(' ', $errors));
        redirect('admin-dashboard.php');
    }

    // Check for duplicate email
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        setFlash('error', 'A user with that email already exists.');
        redirect('admin-dashboard.php');
    }

    // Hash password & insert
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    try {
        $stmtInsert = $pdo->prepare('INSERT INTO users (name, email, role, password) VALUES (?, ?, ?, ?)');
        $stmtInsert->execute([$name, $email, $role, $hashedPassword]);
        setFlash('success', 'User added successfully!');
    } catch (PDOException $e) {
        error_log('Admin add user error: ' . $e->getMessage());
        setFlash('error', 'Failed to add user due to server error.');
    }

    redirect('admin-dashboard.php');
}

// ── EDIT USER (Admin Privilege) ───────────────────────────────
if ($action === 'edit_user') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email = trim(htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'));
    $role = trim($_POST['role'] ?? 'customer');
    $password = $_POST['password'] ?? '';

    if (!$user_id || $user_id < 1) {
        setFlash('error', 'Invalid user ID.');
        redirect('admin-dashboard.php');
    }

    $errors = [];
    if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors[] = 'Name must be between 2 and 120 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (!empty($password) && mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!in_array($role, ['customer', 'manager', 'admin'], true)) {
        $errors[] = 'Invalid role selection.';
    }

    // Prevent changing self role from admin
    if ($user_id === (int)$_SESSION['user_id'] && $role !== 'admin') {
        $errors[] = 'You cannot demote yourself from Administrator role.';
    }

    if (!empty($errors)) {
        setFlash('error', implode(' ', $errors));
        redirect("edit-user.php?id=$user_id");
    }

    // Check for duplicate email of another user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        setFlash('error', 'Another user with that email already exists.');
        redirect("edit-user.php?id=$user_id");
    }

    try {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmtUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?');
            $stmtUpdate->execute([$name, $email, $role, $hashedPassword, $user_id]);
        } else {
            $stmtUpdate = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?');
            $stmtUpdate->execute([$name, $email, $role, $user_id]);
        }
        setFlash('success', 'User updated successfully!');
    } catch (PDOException $e) {
        error_log('Admin edit user error: ' . $e->getMessage());
        setFlash('error', 'Failed to update user due to server error.');
        redirect("edit-user.php?id=$user_id");
    }

    redirect('admin-dashboard.php');
}

// ── DELETE USER (Admin Privilege) ─────────────────────────────
if ($action === 'delete_user') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if (!$user_id || $user_id < 1) {
        setFlash('error', 'Invalid user ID.');
        redirect('admin-dashboard.php');
    }

    // Prevent self deletion
    if ($user_id === (int)$_SESSION['user_id']) {
        setFlash('error', 'You cannot delete your own admin account.');
        redirect('admin-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        setFlash('success', 'User deleted successfully.');
    } catch (PDOException $e) {
        error_log('Admin delete user error: ' . $e->getMessage());
        setFlash('error', 'Failed to delete user.');
    }

    redirect('admin-dashboard.php');
}

// ── ADD PRODUCT (Legacy Admin Support) ────────────────────────
if ($action === 'add_product') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $stock = trim($_POST['stock'] ?? '');

    if (empty($title) || empty($description) || empty($price) || empty($image_url) || empty($stock)) {
        setFlash('error', 'All product fields are required.');
        redirect('admin-dashboard.php');
    }

    $price = filter_var($price, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($stock, FILTER_VALIDATE_INT);

    if ($price === false || $price < 0 || $stock === false || $stock < 0 || !filter_var($image_url, FILTER_VALIDATE_URL)) {
        setFlash('error', 'Invalid product details inputs.');
        redirect('admin-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO products (title, description, price, image_url, stock) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$title, $description, $price, $image_url, $stock]);
        setFlash('success', 'Product added successfully!');
    } catch (PDOException $e) {
        error_log('Add product error: ' . $e->getMessage());
        setFlash('error', 'Failed to add product.');
    }
    redirect('admin-dashboard.php');
}

// ── DELETE PRODUCT (Legacy Admin Support) ─────────────────────
if ($action === 'delete_product') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    if ($product_id && $product_id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$product_id]);
            setFlash('success', 'Product deleted successfully.');
        } catch (PDOException $e) {
            error_log('Delete product error: ' . $e->getMessage());
            setFlash('error', 'Failed to delete product.');
        }
    }
    redirect('admin-dashboard.php');
}

// ── UPDATE STOCK (Legacy Admin Support) ───────────────────────
if ($action === 'update_stock') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    if ($product_id && $stock !== false && $stock >= 0) {
        try {
            $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
            $stmt->execute([$stock, $product_id]);
            setFlash('success', 'Stock updated successfully!');
        } catch (PDOException $e) {
            error_log('Update stock error: ' . $e->getMessage());
            setFlash('error', 'Failed to update stock.');
        }
    }
    redirect('admin-dashboard.php');
}

// Invalid action
setFlash('error', 'Invalid action.');
redirect('admin-dashboard.php');
