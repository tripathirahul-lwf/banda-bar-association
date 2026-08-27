<?php
/**
 * Mahasachiv Chamber Redirection Wrapper
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce Mahasachiv role
requireRole('mahasachiv');

// Redirect to main admin chamber workspace
header("Location: " . SITE_URL . "/admin/rooms/index.php");
exit;
