<?php
/**
 * System Verification Script
 * Verifies all components of the scholarship management system are working correctly
 */

echo "=== Scholarship Management System Verification ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Check database connection
echo "1. Checking database connection...\n";
try {
    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();
    $success[] = "Database connection successful";
    echo "   ✓ Connected to database\n";
} catch (Exception $e) {
    $errors[] = "Database connection failed: " . $e->getMessage();
    echo "   ✗ Failed to connect to database\n";
    exit(1);
}

// 2. Check required tables exist
echo "\n2. Checking database tables...\n";
$requiredTables = [
    'users', 'scholarships', 'applications', 'documents',
    'disbursements', 'feedback', 'notifications', 'announcements',
    'interview_sessions', 'interview_groups', 'interview_assignments',
    'student_profiles', 'eligibility_requirements', 'scholarship_documents',
    'deadline_reminders', 'activations', 'password_resets', 'login_attempts',
    'audit_logs', 'email_logs', 'scholarship_archive'
];

foreach ($requiredTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ $table\n";
    } else {
        $errors[] = "Missing table: $table";
        echo "   ✗ $table (MISSING)\n";
    }
}

// 3. Check old tables are removed
echo "\n3. Checking old tables are removed...\n";
$oldTables = ['interview_slots', 'interview_bookings'];
foreach ($oldTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        $warnings[] = "Old table still exists: $table";
        echo "   ⚠ $table (should be removed)\n";
    } else {
        echo "   ✓ $table (removed)\n";
    }
}

// 4. Check required files exist
echo "\n4. Checking required files...\n";
$requiredFiles = [
    // Core
    'config/db.php',
    'config/email.php',
    
    // Helpers
    'helpers/SecurityHelper.php',
    'helpers/InterviewHelper.php',
    'helpers/AuditHelper.php',
    'helpers/NotificationHelper.php',
    
    // Auth
    'auth/login.php',
    'auth/register.php',
    'auth/logout.php',
    'auth/verify_email.php',
    
    // Student
    'students/dashboard.php',
    'students/scholarships.php',
    'students/applications.php',
    'students/interview.php',
    'students/payouts.php',
    
    // Staff
    'staff/dashboard.php',
    'staff/scholarships.php',
    'staff/applications.php',
    'staff/interview_management.php',
    'staff/interview_group_view.php',
    
    // Admin
    'admin/dashboard.php',
    'admin/scholarships.php',
    'admin/applications.php',
    'admin/interview_management.php',
    'admin/interview_group_view.php',
    'admin/users.php',
    
    // Includes
    'includes/modern-header.php',
    'includes/modern-sidebar.php',
    'includes/modern-footer.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        echo "   ✓ $file\n";
    } else {
        $errors[] = "Missing file: $file";
        echo "   ✗ $file (MISSING)\n";
    }
}

// 5. Check old files are deleted
echo "\n5. Checking old files are deleted...\n";
$oldFiles = [
    'students/interview_booking.php',
    'staff/interview_slots.php',
    'staff/interview_bookings.php',
    'admin/interview_slots.php',
    'admin/interview_bookings.php'
];

foreach ($oldFiles as $file) {
    $fullPath = __DIR__ . '/../' . $file;
    if (file_exists($fullPath)) {
        $warnings[] = "Old file still exists: $file";
        echo "   ⚠ $file (should be deleted)\n";
    } else {
        echo "   ✓ $file (deleted)\n";
    }
}

// 6. Check PHP syntax errors
echo "\n6. Checking for PHP syntax errors...\n";
$phpFiles = glob(__DIR__ . '/../{students,staff,admin,auth,helpers,controllers}/*.php', GLOB_BRACE);
$syntaxErrors = 0;
foreach ($phpFiles as $file) {
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return);
    if ($return !== 0) {
        $syntaxErrors++;
        $errors[] = "Syntax error in: " . basename($file);
        echo "   ✗ " . basename($file) . "\n";
    }
}
if ($syntaxErrors === 0) {
    echo "   ✓ No syntax errors found\n";
}

// 7. Check database integrity
echo "\n7. Checking database integrity...\n";
try {
    // Check for orphaned records
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE u.id IS NULL
    ");
    $orphanedApps = $stmt->fetchColumn();
    if ($orphanedApps > 0) {
        $warnings[] = "$orphanedApps orphaned applications found";
        echo "   ⚠ $orphanedApps orphaned applications\n";
    } else {
        echo "   ✓ No orphaned applications\n";
    }
    
    // Check for orphaned interview assignments
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM interview_assignments ia 
        LEFT JOIN applications a ON ia.application_id = a.id 
        WHERE a.id IS NULL
    ");
    $orphanedAssignments = $stmt->fetchColumn();
    if ($orphanedAssignments > 0) {
        $warnings[] = "$orphanedAssignments orphaned interview assignments found";
        echo "   ⚠ $orphanedAssignments orphaned interview assignments\n";
    } else {
        echo "   ✓ No orphaned interview assignments\n";
    }
    
} catch (Exception $e) {
    $warnings[] = "Could not check database integrity: " . $e->getMessage();
    echo "   ⚠ Could not complete integrity check\n";
}

// 8. Summary
echo "\n=== Verification Summary ===\n\n";
echo "Errors: " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n";
echo "Success: " . count($success) . "\n\n";

if (count($errors) > 0) {
    echo "ERRORS:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ $warning\n";
    }
    echo "\n";
}

if (count($errors) === 0) {
    echo "✅ SYSTEM STATUS: OPERATIONAL\n";
    echo "\nAll critical components are working correctly.\n";
    if (count($warnings) > 0) {
        echo "Some warnings were found but they don't affect core functionality.\n";
    }
} else {
    echo "❌ SYSTEM STATUS: ERRORS FOUND\n";
    echo "\nPlease fix the errors listed above before using the system.\n";
}

echo "\n=== Verification Complete ===\n";
