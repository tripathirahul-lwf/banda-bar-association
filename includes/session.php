<?php
/**
 * Session Security, Timeout and HTTP Security Headers Management
 * District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

// 1. Security Headers Configuration
if (!headers_sent()) {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: same-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; frame-ancestors 'none';");
}

// 2. Session Inactivity Timeout Configuration (30 minutes)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800); // 1800 seconds = 30 minutes
}

// Check session status and verify if logged in user is inactive
if (isset($_SESSION['auth']) && isset($_SESSION['auth']['logged_in']) && $_SESSION['auth']['logged_in'] === true) {
    $now = time();
    if (isset($_SESSION['auth']['last_activity'])) {
        $inactive_duration = $now - $_SESSION['auth']['last_activity'];
        if ($inactive_duration > SESSION_TIMEOUT) {
            // Clear session variables and cookies
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            
            // Restart session for flash notification
            session_name(SESSION_NAME);
            session_start();
            $_SESSION['flash_messages']['danger'] = "सुरक्षा कारणों से आपका सत्र समाप्त हो गया है। कृपया पुनः लॉगिन करें। (Your session has expired. Please log in again.)";
            
            header("Location: " . SITE_URL . "/login.php");
            exit();
        }
    }
    // Update last activity timestamp
    $_SESSION['auth']['last_activity'] = $now;
}
