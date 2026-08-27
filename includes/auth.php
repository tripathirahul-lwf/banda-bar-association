<?php
/**
 * Authentication and Authorization Helper Library
 * District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Active Session & Privilege Validation Check (Requirement 11, 14 & 38)
if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['auth']) && isset($_SESSION['auth']['user_id'])) {
    $db_conn = Database::getConnection();
    if ($db_conn) {
        try {
            $sess_stmt = $db_conn->prepare("SELECT role, status FROM users WHERE id = ?");
            $sess_stmt->execute([$_SESSION['auth']['user_id']]);
            $db_user = $sess_stmt->fetch();
            if (!$db_user || $db_user['status'] !== 'active' || $db_user['role'] !== $_SESSION['auth']['role']) {
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
                
                // Restart session for flash message
                session_name(SESSION_NAME);
                session_start();
                $_SESSION['flash_messages']['danger'] = "सुरक्षा कारणों से आपका सत्र समाप्त हो गया है। कृपया पुनः लॉगिन करें। (Your session has expired or privilege has changed.)";
                
                header("Location: " . SITE_URL . "/login.php");
                exit();
            }
        } catch (PDOException $e) {}
    }
}

/**
 * Check if the user is authenticated.
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['auth']) && isset($_SESSION['auth']['logged_in']) && $_SESSION['auth']['logged_in'] === true;
}

/**
 * Retrieve the current authenticated user data.
 * 
 * @return array|null
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['auth'];
}

/**
 * Retrieve the role of the current authenticated user.
 * 
 * @return string|null
 */
function currentRole() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['auth']['role'];
}

/**
 * Enforce that the user must be logged in.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('danger', 'कृपया आगे बढ़ने से पहले लॉगिन करें। (Please log in to continue.)');
        redirect(SITE_URL . '/login.php');
    }
}

/**
 * Enforce that the user must have one of the specified roles.
 * Redirects to 403 Access Denied if unauthorized.
 * 
 * @param string|array $roles Allowed role or array of allowed roles.
 */
function requireRole($roles) {
    requireLogin();
    
    $current_role = currentRole();
    $is_authorized = false;
    
    if (is_array($roles)) {
        $is_authorized = in_array($current_role, $roles);
    } else {
        $is_authorized = ($current_role === $roles);
    }
    
    if (!$is_authorized) {
        http_response_code(403);
        redirect(SITE_URL . '/403.php');
    }
}

/**
 * Check if current user matches a specific role.
 * 
 * @param string $role Role name.
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return currentRole() === $role;
}

/**
 * Terminate user session safely.
 */
function logoutUser() {
    if (session_status() !== PHP_SESSION_NONE) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}

/**
 * Redirect logged in users to their proper role dashboard.
 */
function redirectByRole() {
    if (isLoggedIn()) {
        $role = currentRole();
        redirect(SITE_URL . '/' . $role . '/dashboard.php');
    }
}

// ==========================================
// Backward Compatibility Aliases for Phase 1
// ==========================================
function is_logged_in() {
    return isLoggedIn();
}

function has_role($roles) {
    return hasRole($roles);
}

function require_role($roles) {
    requireRole($roles);
}

function logout_user() {
    logoutUser();
}
