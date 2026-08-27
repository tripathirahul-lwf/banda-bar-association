<?php
/**
 * Secure Logout Action
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Terminate user session
logoutUser();

// Start session again for the success notification
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

setFlash('success', 'आप सफलतापूर्वक लॉग आउट हो गए हैं। (You have been logged out successfully.)');
redirect(SITE_URL . '/login.php');
