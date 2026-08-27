<?php
/**
 * Mahasachiv Operational Management Redirect
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce Mahasachiv Role
requireRole('mahasachiv');

// Redirect to unified Admin/Mahasachiv management workspace
redirect(SITE_URL . '/admin/wakalatnama/index.php');
