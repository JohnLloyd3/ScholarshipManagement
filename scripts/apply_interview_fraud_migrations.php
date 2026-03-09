<?php
/**
 * Apply Interview Scheduling and Fraud Detection Migrations
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Applying Interview & Fraud Detection Migrations ===\n\n";

try {
    $pdo = getPDO();
    
    // Read migration file
    $migrationFile = __DIR__ . '/../migrations/2026_03_09_interview_fraud_system.sql';
    
    if (!file_exists($migrationFile)) {
        die("Error: Migration file not found at $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "Found " . count($statements) . " SQL statements to execute.\n\n";
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $pdo->exec($statement);
            $success++;
            
            // Extract table name for better logging
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Created table: {$matches[1]}\n";
            } elseif (preg_match('/ALTER TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Altered table: {$matches[1]}\n";
            } else {
                echo "✓ Executed statement " . ($index + 1) . "\n";
            }
        } catch (PDOException $e) {
            $errors++;
            // Check if error is "already exists" - that's okay
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠ Skipped (already exists): Statement " . ($index + 1) . "\n";
            } else {
                echo "✗ Error in statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Migration Summary ===\n";
    echo "✓ Successful: $success\n";
    echo "✗ Errors: $errors\n";
    
    // Verify tables exist
    echo "\n=== Verifying Tables ===\n";
    $tables = ['interview_slots', 'interview_bookings', 'fraud_alerts'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' NOT found\n";
        }
    }
    
    // Check new columns
    echo "\n=== Verifying New Columns ===\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'file_hash'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Column 'file_hash' added to documents table\n";
    } else {
        echo "✗ Column 'file_hash' NOT found in documents table\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM applications LIKE 'fraud_score'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Column 'fraud_score' added to applications table\n";
    } else {
        echo "✗ Column 'fraud_score' NOT found in applications table\n";
    }
    
    echo "\n✅ Migration process completed!\n";
    echo "\nNext steps:\n";
    echo "1. Access Interview Slots: admin/interview_slots.php\n";
    echo "2. Access Fraud Detection: admin/fraud_detection.php\n";
    echo "3. Students can book interviews: member/interview_booking.php\n";
    
} catch (Exception $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
