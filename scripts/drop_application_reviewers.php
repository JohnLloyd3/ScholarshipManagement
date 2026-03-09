<?php
/**
 * Drop the legacy application_reviewers table if it exists.
 * Run: php scripts/drop_application_reviewers.php
 */
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("DROP TABLE IF EXISTS application_reviewers");
    echo "Dropped table application_reviewers (if it existed).\n";
} catch (Exception $e) {
    echo "Failed to drop table: " . $e->getMessage() . "\n";
    exit(1);
}

?>
