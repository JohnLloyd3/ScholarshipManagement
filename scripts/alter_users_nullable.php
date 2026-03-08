<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Determine current database name
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!$dbName) {
    echo "Unable to determine database name.\n";
    exit(1);
}

$columns = ['secret_question' => "VARCHAR(255)", 'secret_answer_hash' => "VARCHAR(255)"];

foreach ($columns as $col => $type) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col');
    $stmt->execute([':db' => $dbName, ':tbl' => 'users', ':col' => $col]);
    $exists = (bool)$stmt->fetchColumn();
    if ($exists) {
        try {
            $sql = "ALTER TABLE users MODIFY COLUMN {$col} {$type} NULL";
            $pdo->exec($sql);
            echo "Modified column {$col} to be NULLABLE.\n";
        } catch (PDOException $e) {
            echo "Failed to modify {$col}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column {$col} does not exist; skipping.\n";
    }
}

echo "alter_users_nullable completed.\n";
