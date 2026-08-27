<?php
/**
 * DB Seed and Schema executor
 */
require_once __DIR__ . '/../config/config.php';

try {
    // Connect without database selected to run create database query
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Execute schema.sql (contains USE statement and CREATE commands)
    $pdo->exec($sql);
    echo "SUCCESS: Database 'banda_bar' schema and seed data loaded successfully." . PHP_EOL;
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
