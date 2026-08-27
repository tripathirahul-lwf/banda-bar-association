<?php
/**
 * Mahasachiv Bar Fee redirect wrapper
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../includes/auth.php';

// Enforce Mahasachiv Role
requireRole('mahasachiv');

header("Location: " . SITE_URL . "/admin/bar-fee/index.php");
exit;
