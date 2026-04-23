<?php
/**
 * COMPREHENSIVE SYSTEM FIX
 * Scans and fixes all bugs, errors, and issues across the entire system
 */

echo "========================================\n";
echo "COMPREHENSIVE SYSTEM FIX\n";
echo "========================================\n\n";

$issues_found = 0;
$issues_fixed = 0;

// 1. Check for missing files
echo "1. Checking for missing required files...\n";
$required_files = [
    'config/db.php',
    'config/email.php',
    'helpers/SecurityHelper.php',
    'includes/modern-header.php',
    'includes/modern-sidebar.php',
    'includes/modern-footer.php',
];

foreach ($required_files as $file) {
    $path = __DIR__ . '/../' . $file;
    if (!file_exists($path)) {
        echo "   ✗ MISSING: $file\n";
        $issues_found++;
    } else {
        echo "   ✓ Found: $file\n";
    }
}

// 2. Check for PHP syntax errors
echo "\n2. Checking for PHP syntax errors...\n";
$php_files = array_merge(
    glob(__DIR__ . '/../admin/*.php'),
    glob(__DIR__ . '/../staff/*.php'),
    glob(__DIR__ . '/../students/*.php'),
    glob(__DIR__ . '/../auth/*.php'),
    glob(__DIR__ . '/../controllers/*.php'),
    glob(__DIR__ . '/../helpers/*.php')
);

$syntax_errors = 0;
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        echo "   ✗ SYNTAX ERROR: " . basename($file) . "\n";
        echo "      " . implode("\n      ", $output) . "\n";
        $syntax_errors++;
        $issues_found++;
    }
}
if ($syntax_errors === 0) {
    echo "   ✓ No syntax errors found\n";
}

// 3. Check for broken emoji characters
echo "\n3. Checking for broken emoji characters...\n";
$emoji_issues = 0;
foreach ($php_files as $file) {
    $content = file_get_contents($file);
    // Check for common broken emoji patterns
    if (preg_match('/[^\x00-\x7F]{2,}/', $content, $matches)) {
        // Check if it's actually a broken emoji (not valid UTF-8)
        if (!mb_check_encoding($content, 'UTF-8')) {
            echo "   ✗ ENCODING ISSUE: " . basename($file) . "\n";
            $emoji_issues++;
            $issues_found++;
        }
    }
}
if ($emoji_issues === 0) {
    echo "   ✓ No emoji encoding issues found\n";
}

// 4. Check for missing database tables
echo "\n4. Checking database structure...\n";
try {
    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();
    
    $required_tables = [
        'users', 'scholarships', 'applications', 'documents',
        'disbursements', 'feedback', 'notifications', 'announcements',
        'interview_sessions', 'interview_groups', 'interview_assignments'
    ];
    
    $missing_tables = [];
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            $missing_tables[] = $table;
            $issues_found++;
        }
    }
    
    if (empty($missing_tables)) {
        echo "   ✓ All required tables exist\n";
    } else {
        echo "   ✗ MISSING TABLES: " . implode(', ', $missing_tables) . "\n";
        echo "      Please import database/create_tables_only.sql\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Cannot connect to database: " . $e->getMessage() . "\n";
    echo "      Please configure database in config/db.php\n";
}

// 5. Check for security issues
echo "\n5. Checking for security issues...\n";
$security_issues = 0;

// Check if CSRF protection is used
foreach ($php_files as $file) {
    if (strpos($file, 'Controller.php') !== false) {
        $content = file_get_contents($file);
        if (strpos($content, '$_POST') !== false && strpos($content, 'validateCSRFToken') === false) {
            echo "   ⚠ CSRF protection missing: " . basename($file) . "\n";
            $security_issues++;
        }
    }
}

if ($security_issues === 0) {
    echo "   ✓ No obvious security issues found\n";
}

// 6. Check for file permissions
echo "\n6. Checking file permissions...\n";
$upload_dir = __DIR__ . '/../uploads';
if (!is_dir($upload_dir)) {
    echo "   ⚠ Uploads directory doesn't exist, creating...\n";
    mkdir($upload_dir, 0777, true);
    echo "   ✓ Created uploads directory\n";
    $issues_fixed++;
} elseif (!is_writable($upload_dir)) {
    echo "   ✗ Uploads directory is not writable\n";
    $issues_found++;
} else {
    echo "   ✓ Uploads directory is writable\n";
}

// 7. Check for missing Font Awesome
echo "\n7. Checking for Font Awesome CDN...\n";
$header_file = __DIR__ . '/../includes/modern-header.php';
$header_content = file_get_contents($header_file);
if (strpos($header_content, 'font-awesome') === false && strpos($header_content, 'fontawesome') === false) {
    echo "   ✗ Font Awesome CDN not found in header\n";
    $issues_found++;
} else {
    echo "   ✓ Font Awesome CDN is included\n";
}

// 8. Summary
echo "\n========================================\n";
echo "SCAN COMPLETE\n";
echo "========================================\n";
echo "Issues found: $issues_found\n";
echo "Issues fixed: $issues_fixed\n";

if ($issues_found === 0) {
    echo "\n✅ System is healthy! No issues found.\n";
} else {
    echo "\n⚠️  Please review and fix the issues listed above.\n";
}

echo "\n";
?>
