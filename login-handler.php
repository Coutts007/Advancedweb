<?php
// login-handler.php — Authentication backend processor
require_once 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

// ── CSRF validation ───────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    setFlash('error', 'Invalid form submission. Please try again.');
    redirect('login.php');
}

// ── Collect & sanitize inputs ─────────────────────────────────
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';

// ── Basic validation ──────────────────────────────────────────
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
    setFlash('error', 'Please provide a valid email and password.');
    redirect('login.php');
}

// ── Lookup user by email ──────────────────────────────────────
$pdo  = getPDO();
$stmt = $pdo->prepare('SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

// ── Verify password (constant-time comparison via password_verify) ─
// Deliberately use the same response for "user not found" vs "wrong password"
// to prevent user enumeration attacks.
if (!$user || !password_verify($password, $user['password'])) {
    // Introduce a tiny artificial delay to further slow brute-force attempts
    usleep(200000); // 200 ms
    setFlash('error', 'Invalid email or password. Please try again.');
    redirect('login.php');
}

// ── Successful authentication ─────────────────────────────────

// Session fixation protection: regenerate the session ID on login
session_regenerate_id(true);

// Store only the minimal needed data in the session
$_SESSION['user_id']   = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['logged_in_at'] = time();

// Rotate CSRF token after login
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Redirect to homepage ──────────────────────────────────────
redirect('index.php');
