<?php
// profile-handler.php — Process profile details update and password changes
require_once 'db.php';

// Enforce authentication
requireLogin();

// Accept only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    redirect('profile.php');
}

// CSRF validation
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    setFlash('error', 'Invalid form submission. Please try again.');
    redirect('profile.php');
}

$action = $_POST['action'] ?? null;
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic Validation
    if (empty($name) || empty($email)) {
        setFlash('error', 'Name and Email fields are required.');
        redirect('profile.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Please enter a valid email address.');
        redirect('profile.php');
    }

    // Check if email is already taken by another user
    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            setFlash('error', 'This email address is already in use by another account.');
            redirect('profile.php');
        }
    } catch (PDOException $e) {
        error_log('Email unique check error: ' . $e->getMessage());
        setFlash('error', 'An error occurred. Please try again.');
        redirect('profile.php');
    }

    // Fetch user record for password verification if new password is requested
    $updatePassword = false;
    if (!empty($new_password) || !empty($current_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            setFlash('error', 'You must provide your current password to set a new password.');
            redirect('profile.php');
        }
        if (empty($new_password)) {
            setFlash('error', 'New password cannot be empty.');
            redirect('profile.php');
        }
        if ($new_password !== $confirm_password) {
            setFlash('error', 'New password confirmation does not match.');
            redirect('profile.php');
        }
        if (strlen($new_password) < 6) {
            setFlash('error', 'New password must be at least 6 characters long.');
            redirect('profile.php');
        }

        // Verify current password
        try {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user_id]);
            $userRow = $stmt->fetch();
            if (!$userRow || !password_verify($current_password, $userRow['password'])) {
                setFlash('error', 'Current password is incorrect.');
                redirect('profile.php');
            }
            $updatePassword = true;
        } catch (PDOException $e) {
            error_log('Password fetch error: ' . $e->getMessage());
            setFlash('error', 'Failed to verify password.');
            redirect('profile.php');
        }
    }

    // Perform update
    try {
        if ($updatePassword) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?');
            $stmt->execute([$name, $email, $hashedPassword, $user_id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, $user_id]);
        }

        // Update session info
        $_SESSION['user_name'] = $name;

        setFlash('success', 'Profile updated successfully!');
    } catch (PDOException $e) {
        error_log('Profile update error: ' . $e->getMessage());
        setFlash('error', 'Failed to update profile details. Please try again.');
    }

    redirect('profile.php');
}

// Invalid action
setFlash('error', 'Invalid action.');
redirect('profile.php');
