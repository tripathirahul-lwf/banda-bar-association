<?php
/**
 * Database connection file for District Bar Association, Banda
 * Uses PDO for clean, secure database access.
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

require_once __DIR__ . '/config.php';

class Database {
    private static $connection = null;

    /**
     * Get a PDO database connection instance.
     * 
     * @return PDO|null Returns PDO instance if successful, null if failed.
     */
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $port = defined('DB_PORT') && DB_PORT ? DB_PORT : '3306';
                $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                return null;
            }
        }
        return self::$connection;
    }
}
