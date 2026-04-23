<?php
/**
 * Fix all broken emoji markers (??) in PHP files
 */

$fixes = [
    'students/scholarships.php' => [
        ['old' => '?? Available Scholarships', 'new' => '🎓 Available Scholarships']
    ],
    'staff/scholarships.php' => [
        ['old' => '?? Scholarship Management', 'new' => '🎓 Scholarship Management']
    ],
    'staff/search.php' => [
        ['old' => '?? Search', 'new' => '🔍 Search']
    ],
    'admin/announcements.php' => [
        ['old' => '?? Announcements', 'new' => '📢 Announcements']
    ],
    'staff/announcements.php' => [
        ['old' => '?? Announcements', 'new' => '📢 Announcements']
    ]
];

$rootDir = dirname(__DIR__);
$fixed = 0;
$errors = 0;

foreach ($fixes as $file => $replacements) {
    $filePath = $rootDir . '/' . $file;
    
    if (!file_exists($filePath)) {
        echo "❌ File not found: $file\n";
        $errors++;
        continue;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    foreach ($replacements as $replacement) {
        $content = str_replace($replacement['old'], $replacement['new'], $content);
    }
    
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            echo "✅ Fixed: $file\n";
            $fixed++;
        } else {
            echo "❌ Failed to write: $file\n";
            $errors++;
        }
    } else {
        echo "ℹ️  No changes needed: $file\n";
    }
}

echo "\n";
echo "Summary:\n";
echo "✅ Fixed: $fixed files\n";
echo "❌ Errors: $errors files\n";
