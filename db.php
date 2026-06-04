<?php
// ============================================================
// db.php — Central database connection & session bootstrap
// ============================================================

// ── Secure session configuration (must be set BEFORE session_start) ──
ini_set('session.use_strict_mode',    '1');
ini_set('session.use_only_cookies',   '1');
ini_set('session.use_trans_sid',      '0');
ini_set('session.cookie_httponly',    '1');
ini_set('session.cookie_samesite',    'Lax');
// Uncomment the line below when running over HTTPS in production:
// ini_set('session.cookie_secure', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Database credentials ──────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'acommerce');
define('DB_USER', 'root');
define('DB_PASS', '');          // Change in production
define('DB_CHARSET', 'utf8mb4');

// ── PDO connection ────────────────────────────────────────────
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // use real prepared statements
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never expose raw error details to the browser
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'A database error occurred. Please try again later.']));
    }

    return $pdo;
}

// ── Convenience helpers ───────────────────────────────────────

/**
 * Redirect to a URL and terminate execution.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Store a one-time flash message in the session.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message.
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Return true if the current visitor is authenticated.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require authentication — redirect guests to login.php.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to access that page.');
        redirect('login.php');
    }
}

/**
 * Return true if the current user is an admin.
 */
function isAdmin(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Require admin access — redirect non-admins to homepage.
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        setFlash('error', 'You do not have permission to access that page.');
        redirect('index.php');
    }
}
