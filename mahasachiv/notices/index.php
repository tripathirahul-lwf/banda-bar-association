<?php
/**
 * Secretary Notices Panel Redirect Wrapper
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Enforce Mahasachiv role check
requireRole('mahasachiv');

// Redirect to the unified Admin Notices panel
redirect(SITE_URL . '/admin/notices/index.php');
