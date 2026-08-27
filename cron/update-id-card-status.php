<?php
/**
 * Daily Cron Job to Expire Passed ID Cards
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Secure execution: CLI or secret token
$is_cli = (php_sapi_name() === 'cli');
$secret = 'dba_banda_cron_secret_key_2026';
$token = isset($_GET['key']) ? trim($_GET['key']) : '';

if (!$is_cli && $token !== $secret) {
    http_response_code(403);
    exit("Access forbidden. Invalid token.");
}

$db = Database::getConnection();
$expired_count = 0;

if ($db) {
    $expired_count = expireOldIdCards($db);
    $msg = "[" . date('Y-m-d H:i:s') . "] ID Cards cron check executed. Expired $expired_count cards.\n";
    echo $msg;
    
    // Log to system logs or file
    $log_dir = __DIR__ . '/../scratch/logs/';
    if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
    file_put_contents($log_dir . 'cron_id_expiry.log', $msg, FILE_APPEND);
} else {
    echo "Database connection failed.\n";
}
