<?php
require_once __DIR__ . '/db.php';
try {
    $pdo = getPDO();
    echo "OK: Connected to database '" . DB_NAME . "' via DSN: " . DB_DSN . "<br>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: <pre>" . implode("\n", $tables) . "</pre>";
} catch (PDOException $e) {
    echo "ERROR: " . htmlspecialchars($e->getMessage());
    echo "<p>Check that MySQL is running, credentials in <code>config/db.php</code> are correct, and the PDO MySQL extension is enabled.</p>";
    error_log('[DB TEST] ' . $e->getMessage());
}
