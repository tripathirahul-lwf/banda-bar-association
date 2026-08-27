<?php
/**
 * Redirect index requests to main dashboard file
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../config/config.php';
header("Location: " . SITE_URL . "/member/dashboard.php");
exit();
