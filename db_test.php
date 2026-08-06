<?php
// TEMPORARY diagnostic script. Visit it once in your browser, read the
// exact error, then DELETE this file — it exposes DB error details.
require_once __DIR__ . '/config/config.php';

echo '<pre>';
echo "Trying to connect with:\n";
echo "HOST: " . DB_HOST . "\n";
echo "NAME: " . DB_NAME . "\n";
echo "USER: " . DB_USER . "\n";
echo "PASS length: " . strlen(DB_PASS) . " characters\n\n";

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "SUCCESS: connected to the database.\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables found (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        echo " - $t\n";
    }
    if (!$tables) {
        echo "(none — you still need to import database/schema.sql)\n";
    }
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
echo '</pre>';
