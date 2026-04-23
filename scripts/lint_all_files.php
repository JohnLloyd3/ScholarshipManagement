<?php
/**
 * COMPREHENSIVE PHP LINTER
 * Uses full path to PHP executable to check all PHP files for syntax errors
 */

// Path to PHP executable
$php_exe = 'c:\xampp\php\php.exe';

// Directories to scan
$directories = [
    __DIR__ . '/../admin',
    __DIR__ . '/../staff',
    __DIR__ . '/../students',
    __DIR__ . '/../auth',
    __DIR__ . '/../controllers',
    __DIR__ . '/../helpers',
    __DIR__ . '/../includes',
    __DIR__ . '/../cron',
    __DIR__ . '/../config',
];

$total_files = 0;
$error_files = 0;
$errors = [];

echo "========================================\n";
echo "PHP SYNTAX CHECK\n";
echo "========================================\n\n";

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.php');
    
    foreach ($files as $file) {
        $total_files++;
        
        // Skip backup files
        if (strpos($file, '.emoji_backup') !== false) continue;
        
        // Use shell_exec with full path to PHP
        $output = shell_exec("\"$php_exe\" -l \"$file\" 2>&1");
        
        // Check if there's a parse error
        if (strpos($output, 'Parse error') !== false || strpos($output, 'Syntax error') !== false) {
            $error_files++;
            $errors[] = [
                'file' => basename($file),
                'path' => str_replace(__DIR__ . '/..', '', $file),
                'error' => trim($output)
            ];
        }
    }
}

// Display results
if (empty($errors)) {
    echo "✓ All $total_files PHP files have correct syntax\n";
} else {
    echo "✗ Found $error_files files with syntax errors:\n\n";
    foreach ($errors as $error) {
        echo "File: " . $error['path'] . "\n";
        echo "Error: " . $error['error'] . "\n";
        echo "---\n";
    }
}

echo "\n========================================\n";
echo "SCAN COMPLETE\n";
echo "========================================\n";
echo "Total files checked: $total_files\n";
echo "Files with errors: $error_files\n";

?>
