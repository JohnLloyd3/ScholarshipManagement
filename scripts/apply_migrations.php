<?php
require_once __DIR__ . '/../config/db.php';

// Simple migration runner: executes SQL files in migrations/ directory in alphabetical order
$dir = __DIR__ . '/../migrations';
$pdo = getPDO();
$files = glob($dir . '/*.sql');
sort($files);
foreach ($files as $f) {
    echo "Applying migration: " . basename($f) . "\n";
    $sql = file_get_contents($f);
    try {
        $pdo->exec($sql);
        echo "  OK\n";
    } catch (PDOException $e) {
        echo "  FAILED: " . $e->getMessage() . "\n";
    }
}

echo "Migrations complete.\n";
