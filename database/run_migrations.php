<?php
/**
 * Safe Database Migration Runner
 * District Bar Association, Banda
 * Established: 1937
 */

// Prevent public HTTP access (cli execution only, or restricted to logged in admin)
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../includes/auth.php';
    if (!isLoggedIn() || currentRole() !== 'admin') {
        http_response_code(403);
        exit("प्रवेश निषेध (Access Denied). Run via command line or log in as Admin.");
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Unable to connect to the database.");
    }

    // 1. Create schema_migrations tracking table if not exists (Requirement 51)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Fetch already executed migrations
    $executed = $db->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Scan migrations folder
    $migrationsDir = __DIR__ . '/migrations';
    if (!is_dir($migrationsDir)) {
        throw new Exception("Migrations directory not found.");
    }
    $files = glob($migrationsDir . '/*.sql');
    sort($files); // Ensure alphanumeric execution order

    // Check if the database was already populated before migrations were introduced
    $tablesExist = false;
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->fetch()) {
            $tablesExist = true;
        }
    } catch (Exception $e) {}

    foreach ($files as $file) {
        $migrationName = basename($file);
        
        if (in_array($migrationName, $executed)) {
            continue;
        }

        echo "Processing migration: $migrationName..." . PHP_EOL;

        // Smart bypass for already existing tables
        if ($tablesExist && in_array($migrationName, ['001_schema.sql', '002_phase12.sql'])) {
            $stmt = $db->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
            $stmt->execute([$migrationName]);
            echo "SKIPPED & RECORDED: $migrationName (Tables already exist)." . PHP_EOL;
            continue;
        }

        // Read SQL file
        $sql = file_get_contents($file);
        
        $useTransaction = (stripos($sql, 'CREATE TABLE') === false && stripos($sql, 'ALTER TABLE') === false && stripos($sql, 'DROP TABLE') === false);
        
        if ($useTransaction) {
            $db->beginTransaction();
        }
        
        try {
            $db->exec($sql);
            
            $stmt = $db->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
            $stmt->execute([$migrationName]);
            
            if ($useTransaction && $db->inTransaction()) {
                $db->commit();
            }
            echo "SUCCESS: Applied migration $migrationName." . PHP_EOL;
        } catch (PDOException $e) {
            if ($useTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            echo "FAILED: Migration $migrationName failed: " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    echo "All migrations check completed." . PHP_EOL;

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
