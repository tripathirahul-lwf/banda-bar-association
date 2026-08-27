<?php
/**
 * Daily / Hourly Notices Scheduler & Expiry Task
 * District Bar Association, Banda
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Security check: Allow CLI execution OR request validation via secure token key query param
$is_cli = (php_sapi_name() === 'cli');
$secret_token = 'dba_notice_cron_secret_7719';

if (!$is_cli) {
    $token = $_GET['token'] ?? '';
    if ($token !== $secret_token) {
        http_response_code(403);
        exit("Direct access forbidden. Unauthorized cron key.");
    }
}

$db = Database::getConnection();
if (!$db) {
    exit("CRON ERROR: Database connection failed.\n");
}

try {
    $published = publishScheduledNotices($db);
    $expired = expireNotices($db);

    echo "CRON SUCCESS - [" . date('Y-m-d H:i:s') . "]\n";
    echo "Transitioned Scheduled Notices to Published: $published\n";
    echo "Transitioned Published Notices to Expired: $expired\n";
} catch (Exception $e) {
    error_log("Notice cron failed: " . $e->getMessage());
    echo "CRON FAILED: " . $e->getMessage() . "\n";
}
