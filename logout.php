<?php
// logout.php — Session termination
require_once 'db.php';

// Unset all session variables
$_SESSION = [];

// Delete the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

// Redirect to login with a goodbye flash
// We need a fresh session to store the flash
session_start();
setFlash('success', 'You have been logged out successfully. See you soon!');
redirect('login.php');
