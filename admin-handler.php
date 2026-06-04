<?php
// admin-handler.php — Admin product management backend processor
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

// ── ADD PRODUCT ───────────────────────────────────────────────
if ($action === 'add_product') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $stock = trim($_POST['stock'] ?? '');

    // Validation
    if (empty($title) || empty($description) || empty($price) || empty($image_url) || empty($stock)) {
        setFlash('error', 'All product fields are required.');
        redirect('admin-dashboard.php');
    }

    $price = filter_var($price, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($stock, FILTER_VALIDATE_INT);

    if ($price === false || $price < 0) {
        setFlash('error', 'Price must be a valid positive number.');
        redirect('admin-dashboard.php');
    }

    if ($stock === false || $stock < 0) {
        setFlash('error', 'Stock must be a valid non-negative integer.');
        redirect('admin-dashboard.php');
    }

    // Validate image URL format (basic check)
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        setFlash('error', 'Image URL must be a valid URL.');
        redirect('admin-dashboard.php');
    }

    // Insert product
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO products (title, description, price, image_url, stock) 
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $description, $price, $image_url, $stock]);
        setFlash('success', 'Product added successfully!');
    } catch (PDOException $e) {
        error_log('Add product error: ' . $e->getMessage());
        setFlash('error', 'Failed to add product. Please try again.');
    }

    redirect('admin-dashboard.php');
}

// ── DELETE PRODUCT ────────────────────────────────────────────
if ($action === 'delete_product') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if (!$product_id || $product_id < 1) {
        setFlash('error', 'Invalid product ID.');
        redirect('admin-dashboard.php');
    }

    // Verify product exists
    $stmt = $pdo->prepare('SELECT id, title FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        setFlash('error', 'Product not found.');
        redirect('admin-dashboard.php');
    }

    // Delete product (cascade deletes related order_items due to foreign key)
    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$product_id]);
        setFlash('success', 'Product "' . htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8') . '" deleted successfully!');
    } catch (PDOException $e) {
        error_log('Delete product error: ' . $e->getMessage());
        setFlash('error', 'Failed to delete product. Please try again.');
    }

    redirect('admin-dashboard.php');
}

// ── UPDATE STOCK ──────────────────────────────────────────────
if ($action === 'update_stock') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    if (!$product_id || $product_id < 1 || $stock === false || $stock < 0) {
        setFlash('error', 'Invalid product ID or stock value.');
        redirect('admin-dashboard.php');
    }

    // Verify product exists
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        setFlash('error', 'Product not found.');
        redirect('admin-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
        $stmt->execute([$stock, $product_id]);
        setFlash('success', 'Stock updated successfully!');
    } catch (PDOException $e) {
        error_log('Update stock error: ' . $e->getMessage());
        setFlash('error', 'Failed to update stock. Please try again.');
    }

    redirect('admin-dashboard.php');
}

// Invalid action
setFlash('error', 'Invalid action.');
redirect('admin-dashboard.php');
