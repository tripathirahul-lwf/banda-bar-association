<?php
/**
 * Member Receipt Secure Wrapper
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../../includes/auth.php';

// Enforce Member Role
requireRole('member');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    die('अवैध रसीद अनुरोध।');
}

// Redirect to core receipt print layout which holds matching member security checks
header("Location: " . SITE_URL . "/admin/bar-fee/receipt.php?id=" . $id);
exit;
