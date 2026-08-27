<?php
/**
 * Mahasachiv (Secretary) Fund Redirector
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Enforce Mahasachiv Role
requireRole('mahasachiv');

// Redirect to shared admin workspace
header('Location: ' . SITE_URL . '/admin/association-fund/index.php');
exit();
