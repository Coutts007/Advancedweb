<?php
// manager-handler.php — Store Manager operations processor
require_once 'db.php';

// Enforce authentication and manager role
requireManager();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    redirect('manager-dashboard.php');
}

// ── CSRF validation ───────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    setFlash('error', 'Invalid form submission. Please try again.');
    redirect('manager-dashboard.php');
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
    $currency = trim($_POST['currency'] ?? 'Ksh');

    // Validation
    if (empty($title) || empty($description) || empty($price) || empty($image_url) || $stock === '' || empty($currency)) {
        setFlash('error', 'All product fields are required.');
        redirect('manager-dashboard.php');
    }

    $price = filter_var($price, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($stock, FILTER_VALIDATE_INT);

    if ($price === false || $price < 0) {
        setFlash('error', 'Price must be a valid positive number.');
        redirect('manager-dashboard.php');
    }

    if ($stock === false || $stock < 0) {
        setFlash('error', 'Stock must be a valid non-negative integer.');
        redirect('manager-dashboard.php');
    }

    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        setFlash('error', 'Image URL must be a valid URL.');
        redirect('manager-dashboard.php');
    }

    if (mb_strlen($currency) > 10) {
        setFlash('error', 'Currency abbreviation is too long (max 10 characters).');
        redirect('manager-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO products (title, description, price, image_url, stock, currency) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $description, $price, $image_url, $stock, $currency]);
        setFlash('success', 'Product added successfully!');
    } catch (PDOException $e) {
        error_log('Add product error: ' . $e->getMessage());
        setFlash('error', 'Failed to add product. Please try again.');
    }

    redirect('manager-dashboard.php');
}

// ── EDIT PRODUCT ──────────────────────────────────────────────
if ($action === 'edit_product') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $currency = trim($_POST['currency'] ?? 'Ksh');

    if (!$product_id || $product_id < 1) {
        setFlash('error', 'Invalid product ID.');
        redirect('manager-dashboard.php');
    }

    if (empty($title) || empty($description) || empty($price) || empty($image_url) || $stock === '' || empty($currency)) {
        setFlash('error', 'All fields are required.');
        redirect("edit-product.php?id=$product_id");
    }

    $price = filter_var($price, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($stock, FILTER_VALIDATE_INT);

    if ($price === false || $price < 0 || $stock === false || $stock < 0) {
        setFlash('error', 'Price and stock must be valid non-negative numbers.');
        redirect("edit-product.php?id=$product_id");
    }

    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        setFlash('error', 'Image URL must be a valid URL.');
        redirect("edit-product.php?id=$product_id");
    }

    if (mb_strlen($currency) > 10) {
        setFlash('error', 'Currency abbreviation is too long (max 10 characters).');
        redirect("edit-product.php?id=$product_id");
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE products SET title = ?, description = ?, price = ?, image_url = ?, stock = ?, currency = ? WHERE id = ?'
        );
        $stmt->execute([$title, $description, $price, $image_url, $stock, $currency, $product_id]);
        setFlash('success', 'Product updated successfully!');
    } catch (PDOException $e) {
        error_log('Edit product error: ' . $e->getMessage());
        setFlash('error', 'Failed to update product. Please try again.');
        redirect("edit-product.php?id=$product_id");
    }

    redirect('manager-dashboard.php');
}

// ── UPDATE STOCK ──────────────────────────────────────────────
if ($action === 'update_stock') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!$product_id || $product_id < 1 || $stock === false || $stock < 0) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID or stock value.']);
            exit;
        }
        setFlash('error', 'Invalid product ID or stock value.');
        redirect('manager-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare('UPDATE products SET stock = ? WHERE id = ?');
        $stmt->execute([$stock, $product_id]);
        
        if ($isAjax) {
            $pending_count = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
            $products_count = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $low_stock_count = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10")->fetchColumn();

            $status = 'In Stock';
            $status_class = 'product-status--in-stock';
            if ($stock == 0) {
                $status = 'Out of Stock';
                $status_class = 'product-status--out-of-stock';
            } elseif ($stock <= 10) {
                $status = 'Low Stock';
                $status_class = 'product-status--low-stock';
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Stock updated successfully!',
                'status_text' => "$status ($stock)",
                'status_class' => $status_class,
                'stats' => [
                    'pending_checkouts' => $pending_count,
                    'total_products' => $products_count,
                    'low_stock_items' => $low_stock_count
                ]
            ]);
            exit;
        }

        setFlash('success', 'Stock updated successfully!');
    } catch (PDOException $e) {
        error_log('Update stock error: ' . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update stock.']);
            exit;
        }
        setFlash('error', 'Failed to update stock.');
    }

    redirect('manager-dashboard.php');
}

// ── DELETE PRODUCT ────────────────────────────────────────────
if ($action === 'delete_product') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!$product_id || $product_id < 1) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
            exit;
        }
        setFlash('error', 'Invalid product ID.');
        redirect('manager-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$product_id]);

        if ($isAjax) {
            $pending_count = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
            $products_count = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $low_stock_count = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10")->fetchColumn();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Product deleted successfully.',
                'stats' => [
                    'pending_checkouts' => $pending_count,
                    'total_products' => $products_count,
                    'low_stock_items' => $low_stock_count
                ]
            ]);
            exit;
        }

        setFlash('success', 'Product deleted successfully.');
    } catch (PDOException $e) {
        error_log('Delete product error: ' . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to delete product.']);
            exit;
        }
        setFlash('error', 'Failed to delete product.');
    }

    redirect('manager-dashboard.php');
}

// ── CONFIRM CHECKOUT ──────────────────────────────────────────
if ($action === 'confirm_checkout') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!$order_id || $order_id < 1) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
            exit;
        }
        setFlash('error', 'Invalid order ID.');
        redirect('manager-dashboard.php');
    }

    try {
        $pdo->beginTransaction();

        // Fetch order and lock it
        $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Order not found.");
        }

        if ($order['status'] !== 'pending') {
            throw new Exception("Order is already " . $order['status'] . ".");
        }

        // Fetch order items
        $stmtItems = $pdo->prepare(
            "SELECT product_id, quantity FROM order_items WHERE order_id = ?"
        );
        $stmtItems->execute([$order_id]);
        $items = $stmtItems->fetchAll();

        // Lock & check stock for all items
        $stmtCheck = $pdo->prepare("SELECT id, title, stock FROM products WHERE id = ? FOR UPDATE");
        foreach ($items as $item) {
            $stmtCheck->execute([$item['product_id']]);
            $product = $stmtCheck->fetch();

            if (!$product) {
                throw new Exception("One of the products in the order no longer exists.");
            }

            if ($product['stock'] < $item['quantity']) {
                throw new Exception("Insufficient stock for product: " . $product['title']);
            }
        }

        // Decrement stock and update order status
        $stmtDecrement = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        foreach ($items as $item) {
            $stmtDecrement->execute([$item['quantity'], $item['product_id']]);
        }

        $stmtConfirm = $pdo->prepare("UPDATE orders SET status = 'confirmed' WHERE id = ?");
        $stmtConfirm->execute([$order_id]);

        $pdo->commit();

        if ($isAjax) {
            $pending_count = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
            $products_count = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $low_stock_count = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10")->fetchColumn();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Checkout #' . $order_id . ' confirmed successfully!',
                'stats' => [
                    'pending_checkouts' => $pending_count,
                    'total_products' => $products_count,
                    'low_stock_items' => $low_stock_count
                ]
            ]);
            exit;
        }

        setFlash('success', 'Checkout #' . $order_id . ' confirmed successfully!');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Confirm checkout error: ' . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to confirm checkout: ' . $e->getMessage()]);
            exit;
        }
        setFlash('error', 'Failed to confirm checkout: ' . $e->getMessage());
    }

    redirect('manager-dashboard.php');
}

// ── CANCEL CHECKOUT ───────────────────────────────────────────
if ($action === 'cancel_checkout') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if (!$order_id || $order_id < 1) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
            exit;
        }
        setFlash('error', 'Invalid order ID.');
        redirect('manager-dashboard.php');
    }

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$order_id]);

        if ($isAjax) {
            $pending_count = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
            $products_count = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $low_stock_count = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 10")->fetchColumn();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Checkout #' . $order_id . ' cancelled.',
                'stats' => [
                    'pending_checkouts' => $pending_count,
                    'total_products' => $products_count,
                    'low_stock_items' => $low_stock_count
                ]
            ]);
            exit;
        }

        setFlash('success', 'Checkout #' . $order_id . ' cancelled.');
    } catch (PDOException $e) {
        error_log('Cancel checkout error: ' . $e->getMessage());
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to cancel checkout.']);
            exit;
        }
        setFlash('error', 'Failed to cancel checkout.');
    }

    redirect('manager-dashboard.php');
}

// Invalid action
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
setFlash('error', 'Invalid action.');
redirect('manager-dashboard.php');
