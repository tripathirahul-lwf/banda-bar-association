<?php
/**
 * Dynamic Database Creator & Migration Seeder Bootstrapper
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../config/config.php';

try {
    // 1. Load database parameters manually from config to bypass selected DB
    // Read from env values directly loaded in config.php
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3307';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
    $dbname = getenv('DB_NAME') ?: 'banda_bar';

    echo "Connecting to MySQL server on host: $host, port: $port..." . PHP_EOL;

    // 2. Establish connection without database selected
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected successfully to MySQL server!" . PHP_EOL;

    // 3. Create database if not exists
    echo "Creating database if not exists: `$dbname`..." . PHP_EOL;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `$dbname` created or verified successfully!" . PHP_EOL;

    // 4. Connect to database to verify it exists
    $pdo->exec("USE `$dbname`");
    echo "Using database: `$dbname`" . PHP_EOL;

} catch (PDOException $e) {
    echo "ERROR CREATING DATABASE: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// 5. Run migrations automatically
echo PHP_EOL . "Starting database migrations..." . PHP_EOL;
require_once __DIR__ . '/run_migrations.php';
