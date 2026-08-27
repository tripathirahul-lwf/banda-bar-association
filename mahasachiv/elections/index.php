<?php
/**
 * Mahasachiv Election Redirection Wrapper
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce Mahasachiv role
requireRole('mahasachiv');

// Redirect to main admin election workspace
header("Location: " . SITE_URL . "/admin/elections/index.php");
exit;
