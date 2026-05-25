<?php
// signup-handler.php — Registration backend processor
require_once 'db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('signup.php');
}

// ── CSRF validation ───────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    setFlash('error', 'Invalid form submission. Please try again.');
    redirect('signup.php');
}

// ── Collect & sanitize inputs ─────────────────────────────────
$name            = trim(htmlspecialchars($_POST['name']            ?? '', ENT_QUOTES, 'UTF-8'));
$email           = trim(htmlspecialchars($_POST['email']           ?? '', ENT_QUOTES, 'UTF-8'));
$password        = $_POST['password']         ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ── Server-side validation ────────────────────────────────────
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

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    redirect('signup.php');
}

// ── Check for duplicate email ─────────────────────────────────
$pdo  = getPDO();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    setFlash('error', 'An account with that email address already exists. Please log in or use a different email.');
    redirect('signup.php');
}

// ── Hash password & insert user ───────────────────────────────
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$insert = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');

try {
    $insert->execute([$name, $email, $hashedPassword]);
} catch (PDOException $e) {
    error_log('Signup insert error: ' . $e->getMessage());
    setFlash('error', 'Registration failed due to a server error. Please try again.');
    redirect('signup.php');
}

// ── Rotate CSRF token after successful use ────────────────────
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Success: redirect to login ────────────────────────────────
setFlash('success', 'Account created successfully! Please log in to continue.');
redirect('login.php');
