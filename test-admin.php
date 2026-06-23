<?php
// test-admin.php — Simple test to verify admin functionality
require_once 'db.php';

// Test database connection
try {
    $pdo = getPDO();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test admin user exists
$stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['admin@acommerce.local']);
$admin_user = $stmt->fetch();

if ($admin_user && $admin_user['role'] === 'admin') {
    echo "✓ Admin user exists: " . $admin_user['name'] . " (ID: " . $admin_user['id'] . ")\n";
} else {
    echo "✗ Admin user not found or not admin\n";
    exit(1);
}

// Test customer user exists
$stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['customer@acommerce.local']);
$customer = $stmt->fetch();

if ($customer && $customer['role'] === 'customer') {
    echo "✓ Customer user exists: " . $customer['name'] . " (ID: " . $customer['id'] . ")\n";
} else {
    echo "✗ Customer user not found\n";
    exit(1);
}

// Test products table
$stmt = $pdo->query('SELECT COUNT(*) as count FROM products');
$result = $stmt->fetch();
echo "✓ Products in database: " . $result['count'] . "\n";

// Test orders table
$stmt = $pdo->query('SELECT COUNT(*) as count FROM orders');
$result = $stmt->fetch();
echo "✓ Orders in database: " . $result['count'] . "\n";

// Test order_items table
$stmt = $pdo->query('SELECT COUNT(*) as count FROM order_items');
$result = $stmt->fetch();
echo "✓ Order items in database: " . $result['count'] . "\n";

// Test sales stats
$stmt = $pdo->query('SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue FROM orders');
$stats = $stmt->fetch();
echo "✓ Sales stats - Orders: " . $stats['order_count'] . ", Revenue: $" . number_format((float)$stats['total_revenue'], 2) . "\n";

// Test required functions exist
if (function_exists('isAdmin') && function_exists('requireAdmin')) {
    echo "✓ Admin functions exist (isAdmin, requireAdmin)\n";
} else {
    echo "✗ Admin functions not found\n";
    exit(1);
}

// Test file existence
$files = [
    'admin-dashboard.php',
    'admin-handler.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ File exists: $file\n";
    } else {
        echo "✗ File not found: $file\n";
        exit(1);
    }
}

echo "\n✅ All tests passed!\n";
