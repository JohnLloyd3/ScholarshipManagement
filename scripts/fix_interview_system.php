<?php
/**
 * Interview System Fix Script
 * Cleans up old interview system files and ensures new system is properly configured
 */

echo "=== Interview System Fix Script ===\n\n";

// Files to delete (old interview system)
$filesToDelete = [
    __DIR__ . '/../students/interview_booking.php',
    __DIR__ . '/../staff/interview_slots.php',
    __DIR__ . '/../staff/interview_bookings.php',
    __DIR__ . '/../admin/interview_slots.php',
    __DIR__ . '/../admin/interview_bookings.php'
];

echo "Step 1: Removing old interview system files...\n";
foreach ($filesToDelete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "  ✓ Deleted: " . basename($file) . "\n";
        } else {
            echo "  ✗ Failed to delete: " . basename($file) . "\n";
        }
    } else {
        echo "  - Already removed: " . basename($file) . "\n";
    }
}

echo "\nStep 2: Verifying new interview system files exist...\n";
$requiredFiles = [
    __DIR__ . '/../students/interview.php',
    __DIR__ . '/../staff/interview_management.php',
    __DIR__ . '/../staff/interview_group_view.php',
    __DIR__ . '/../admin/interview_management.php',
    __DIR__ . '/../admin/interview_group_view.php',
    __DIR__ . '/../helpers/InterviewHelper.php'
];

$allFilesExist = true;
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "  ✓ Found: " . basename($file) . "\n";
    } else {
        echo "  ✗ Missing: " . basename($file) . "\n";
        $allFilesExist = false;
    }
}

echo "\nStep 3: Checking database tables...\n";
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getPDO();
    
    // Check new tables exist
    $newTables = ['interview_sessions', 'interview_groups', 'interview_assignments'];
    foreach ($newTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Table exists: $table\n";
        } else {
            echo "  ✗ Table missing: $table (Run database/interview_system_migration.sql)\n";
        }
    }
    
    // Check old tables are removed
    $oldTables = ['interview_slots', 'interview_bookings'];
    foreach ($oldTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ⚠ Old table still exists: $table (Should be removed)\n";
        } else {
            echo "  ✓ Old table removed: $table\n";
        }
    }
    
} catch (Exception $e) {
    echo "  ✗ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Script Complete ===\n";
echo "\nSummary:\n";
echo "- Old interview system files: " . (count(array_filter($filesToDelete, 'file_exists')) === 0 ? "Removed ✓" : "Some remain ✗") . "\n";
echo "- New interview system files: " . ($allFilesExist ? "All present ✓" : "Some missing ✗") . "\n";
echo "- Database tables: Check output above\n";

echo "\nNext steps:\n";
echo "1. If old tables still exist, run: database/interview_system_migration.sql\n";
echo "2. Clear browser cache and reload the application\n";
echo "3. Test the new interview management system\n";
