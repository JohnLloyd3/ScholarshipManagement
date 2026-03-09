<?php
/**
 * Apply all pending migrations to ensure database is up to date
 */
require_once __DIR__ . '/../config/db.php';

echo "=== Applying All Migrations ===\n\n";

$pdo = getPDO();
$migrations_dir = __DIR__ . '/../migrations';

if (!is_dir($migrations_dir)) {
    die("Migrations directory not found!\n");
}

$files = scandir($migrations_dir);
$sql_files = array_filter($files, function($f) {
    return pathinfo($f, PATHINFO_EXTENSION) === 'sql';
});

sort($sql_files);

foreach ($sql_files as $file) {
    echo "Applying: $file\n";
    $sql = file_get_contents($migrations_dir . '/' . $file);
    
    try {
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $stmt) {
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
            $pdo->exec($stmt);
        }
        
        echo "  ✅ Success\n";
    } catch (Exception $e) {
        echo "  ⚠️  Warning: " . $e->getMessage() . "\n";
        // Continue with other migrations even if one fails
    }
    
    echo "\n";
}

echo "=== Migration Complete ===\n";
echo "\nVerifying tables...\n\n";

// Verify critical tables exist
$tables = ['users', 'scholarships', 'applications', 'documents', 'email_logs', 'audit_logs', 'cron_runs', 'deadline_reminders'];

foreach ($tables as $table) {
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "✅ $table: $result rows\n";
    } catch (Exception $e) {
        echo "❌ $table: NOT FOUND\n";
    }
}

echo "\n=== All Done! ===\n";
