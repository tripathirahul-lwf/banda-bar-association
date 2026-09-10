<?php
/**
 * Configuration file for District Bar Association, Banda
 * Established: 1937
 */

// Prevent direct access to config file
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

// Safe Environment Loader function
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim(trim($parts[1]), "\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/../.env');

// Environment configurations with default fallbacks
$app_env = $_ENV['APP_ENV'] ?? 'production';
$app_debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Dynamic Application URL Detection (Auto-detects live domain/subdomain/port/HTTPS/subfolder)
$configured_url = trim($_ENV['APP_URL'] ?? '');
$http_host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$is_localhost_host = empty($http_host) || in_array(strtolower(explode(':', $http_host)[0]), ['localhost', '127.0.0.1', '::1'], true);

// If APP_URL is specified in .env, use it UNLESS it points to localhost while accessed from a live remote domain
if (!empty($configured_url) && !(!$is_localhost_host && stripos($configured_url, 'localhost') !== false)) {
    $app_url = rtrim($configured_url, '/');
} elseif (empty($http_host) && php_sapi_name() === 'cli') {
    // CLI fallback
    $app_url = !empty($configured_url) ? rtrim($configured_url, '/') : 'http://localhost/banda-bar';
} else {
    // Determine Protocol (Support HTTPS direct, Reverse Proxy / Cloudflare / Hostinger load balancer)
    $is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');

    $protocol = $is_https ? 'https://' : 'http://';
    $host = !empty($http_host) ? $http_host : 'localhost';

    // Determine Base Directory Path
    $app_dir = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
    $doc_root = '';

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']);
    } elseif (!empty($_SERVER['SCRIPT_FILENAME']) && !empty($_SERVER['SCRIPT_NAME'])) {
        $script_file = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
        $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        if (substr($script_file, -strlen($script_name)) === $script_name) {
            $doc_root = substr($script_file, 0, -strlen($script_name));
        }
    }

    $subpath = '';
    if (!empty($doc_root)) {
        $doc_root = rtrim($doc_root, '/');
        $app_dir = rtrim($app_dir, '/');
        if (stripos($app_dir, $doc_root) === 0) {
            $subpath = substr($app_dir, strlen($doc_root));
        }
    }

    // Fallback: If root folder (e.g. Hostinger /public_html), check SCRIPT_NAME
    if ($subpath === '' && !empty($_SERVER['SCRIPT_NAME'])) {
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($script_dir === '/' || $script_dir === '\\' || $script_dir === '.') {
            $subpath = '';
        }
    }

    $subpath = '/' . trim(str_replace('\\', '/', $subpath), '/');
    $base_path = ($subpath === '/' ? '' : $subpath);

    $app_url = rtrim($protocol . $host . $base_path, '/');
}

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_port = $_ENV['DB_PORT'] ?? '3306';
$db_name = $_ENV['DB_NAME'] ?? 'banda_bar';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';
$session_timeout = intval($_ENV['SESSION_TIMEOUT'] ?? 1800);
$timezone = $_ENV['TIMEZONE'] ?? 'Asia/Kolkata';

// Set Timezone (Requirement 47)
date_default_timezone_set($timezone);

// Site Details
define('SITE_NAME_EN', 'District Bar Association, Banda');
define('SITE_NAME_HI', 'जिला अधिवक्ता संघ, बांदा');
define('ESTD_YEAR', '1937');
define('SITE_URL', rtrim($app_url, '/'));

// Database Connection Constants
define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

// Session Settings
define('SESSION_NAME', 'dba_banda_session');
define('SESSION_TIMEOUT', $session_timeout);

// Error Reporting & Logging (Requirement 3 & 5)
if ($app_debug) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Set up secure error logging outside public root where possible
ini_set('log_errors', 1);
$log_dir = __DIR__ . '/../storage/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
ini_set('error_log', $log_dir . '/app_error.log');

// Central Error & Exception Handlers (Requirement 4)
function customExceptionHandler($exception) {
    error_log("Unhandled Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(500);
    header("Location: " . SITE_URL . "/500.php");
    exit();
}

function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        header("Location: " . SITE_URL . "/500.php");
        exit();
    }
    return true;
}

if (!$app_debug) {
    set_exception_handler('customExceptionHandler');
    set_error_handler('customErrorHandler');
}

// Secure Session Settings (Requirement 12)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name(SESSION_NAME);
    session_start();
}

// Load Session Security & Inactivity Timeout Manager
require_once __DIR__ . '/../includes/session.php';

// ID Card Configurations
define('ID_CARD_VALIDITY_MONTHS', 24);
define('ID_CARD_REQUIRE_PRESIDENT_APPROVAL', false);
define('ID_CARD_EXPIRY_WARNING_DAYS', 30);

// Wakalatnama Configurations
define('WAKALATNAMA_MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('WAKALATNAMA_REQUIRE_APPROVAL', false);
define('WAKALATNAMA_DOWNLOAD_RATE_LIMIT_SECONDS', 5);

// Notice Configurations
define('NOTICE_REQUIRE_APPROVAL', false);
define('NOTICE_MAX_FILE_SIZE', 5242880); // 5MB in bytes

// Association Fund Configurations
define('ASSOCIATION_FUND_REQUIRE_APPROVAL', true);
define('SHOW_ASSOCIATION_FUND_SUMMARY_PUBLICLY', true);
define('FINANCIAL_ATTACHMENT_MAX_SIZE', 5242880); // 5MB in bytes

// Chamber & Room Rent Configurations (Phase 9)
define('CHAMBER_REQUIRE_PRESIDENT_APPROVAL', false);

// Election Management System Configurations (Phase 10)
define('ELECTION_RESULT_REQUIRE_APPROVAL', false);

// Office Bearer Login Roles Auto Sync (Phase 11)
define('AUTO_SYNC_OFFICE_BEARER_LOGIN_ROLES', false);





