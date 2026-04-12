<?php
/**
 * Automated Design Update Script
 * Updates ALL pages in the system with modern design
 * Run this file once to update the entire system
 */

echo "🎨 Starting Complete Design System Update...\n\n";

// Define all files to update with their configurations
$updates = [
    // MEMBER PAGES
    'member/applications.php' => [
        'title' => 'My Applications - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'member/profile.php' => [
        'title' => 'My Profile - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'member/notifications.php' => [
        'title' => 'Notifications - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'member/apply_scholarship.php' => [
        'title' => 'Apply for Scholarship - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'member/scholarship_view.php' => [
        'title' => 'Scholarship Details - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'member/document_view.php' => [
        'title' => 'Document Viewer - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    
    // STAFF PAGES
    'staff/dashboard.php' => [
        'title' => 'Staff Dashboard - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/scholarships.php' => [
        'title' => 'Manage Scholarships - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/applications.php' => [
        'title' => 'Review Applications - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/application_view.php' => [
        'title' => 'Application Details - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/reports.php' => [
        'title' => 'Reports - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/analytics.php' => [
        'title' => 'Analytics - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/post_scholarship.php' => [
        'title' => 'Post Scholarship - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/scholarship_form.php' => [
        'title' => 'Scholarship Form - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/scholarships_manage.php' => [
        'title' => 'Manage Scholarships - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/pending_applications.php' => [
        'title' => 'Pending Applications - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/documents.php' => [
        'title' => 'Documents - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    // 'staff/audit_logs.php' removed
    'staff/cron.php' => [
        'title' => 'Cron Jobs - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/cron_log.php' => [
        'title' => 'Cron Logs - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'staff/search.php' => [
        'title' => 'Search - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    
    // ADMIN PAGES
    'admin/dashboard.php' => [
        'title' => 'Admin Dashboard - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'admin/users.php' => [
        'title' => 'User Management - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'admin/scholarships.php' => [
        'title' => 'Scholarship Management - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'admin/applications.php' => [
        'title' => 'Application Management - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'admin/announcements.php' => [
        'title' => 'Announcements - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    'admin/analytics.php' => [
        'title' => 'System Analytics - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    // 'admin/activity_logs.php' removed
    // 'admin/email_queue.php' removed
    'admin/scholarship_archive.php' => [
        'title' => 'Scholarship Archive - ScholarHub',
        'has_sidebar' => true,
        'base_path' => '../'
    ],
    
    // AUTH PAGES
    'auth/forgot_password.php' => [
        'title' => 'Forgot Password - ScholarHub',
        'has_sidebar' => false,
        'base_path' => '../'
    ],
    'auth/reset_password.php' => [
        'title' => 'Reset Password - ScholarHub',
        'has_sidebar' => false,
        'base_path' => '../'
    ],
    'auth/applicant_register.php' => [
        'title' => 'Applicant Registration - ScholarHub',
        'has_sidebar' => false,
        'base_path' => '../'
    ],
];

$updated_count = 0;
$failed_count = 0;
$skipped_count = 0;

foreach ($updates as $file => $config) {
    echo "Processing: $file\n";
    
    if (!file_exists($file)) {
        echo "  ⚠️  File not found, skipping\n";
        $skipped_count++;
        continue;
    }
    
    // Read current file
    $content = file_get_contents($file);
    
    // Check if already updated
    if (strpos($content, 'modern-theme.css') !== false) {
        echo "  ✓ Already updated\n";
        $skipped_count++;
        continue;
    }
    
    // Find the PHP opening and closing tags
    $php_end = strpos($content, '?>');
    if ($php_end === false) {
        // No closing PHP tag, find where HTML starts
        preg_match('/<!DOCTYPE|<!doctype|<html/i', $content, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches)) {
            echo "  ❌ Cannot find HTML start\n";
            $failed_count++;
            continue;
        }
        $html_start = $matches[0][1];
    } else {
        $html_start = $php_end + 2;
    }
    
    // Extract PHP logic and HTML
    $php_logic = substr($content, 0, $html_start);
    $html_content = substr($content, $html_start);
    
    // Remove old HTML structure (everything from <!DOCTYPE to </html>)
    preg_match('/(<!DOCTYPE.*?<body[^>]*>)(.*?)(<\/body>\s*<\/html>)/is', $html_content, $html_matches);
    
    if (empty($html_matches)) {
        echo "  ❌ Cannot parse HTML structure\n";
        $failed_count++;
        continue;
    }
    
    $body_content = $html_matches[2];
    
    // Clean up old wrappers
    $body_content = preg_replace('/<div class="dashboard-app">.*?<\/div>/s', '', $body_content);
    $body_content = preg_replace('/<main class="main">.*?<\/main>/s', '', $body_content);
    $body_content = preg_replace('/<div class="container"[^>]*>.*?<\/div>/s', '', $body_content);
    
    // Build new structure
    $new_content = $php_logic;
    $new_content .= "<?php\n";
    $new_content .= "\$page_title = '{$config['title']}';\n";
    $new_content .= "\$base_path = '{$config['base_path']}';\n";
    $new_content .= "require_once __DIR__ . '/{$config['base_path']}includes/modern-header.php';\n";
    
    if ($config['has_sidebar']) {
        $new_content .= "require_once __DIR__ . '/{$config['base_path']}includes/modern-sidebar.php';\n";
    }
    
    $new_content .= "?>\n\n";
    $new_content .= $body_content;
    $new_content .= "\n<?php require_once __DIR__ . '/{$config['base_path']}includes/modern-footer.php'; ?>\n";
    
    // Backup original file
    $backup_file = $file . '.backup';
    copy($file, $backup_file);
    
    // Write new content
    if (file_put_contents($file, $new_content)) {
        echo "  ✅ Updated successfully\n";
        $updated_count++;
    } else {
        echo "  ❌ Failed to write file\n";
        $failed_count++;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎉 Update Complete!\n\n";
echo "✅ Updated: $updated_count files\n";
echo "⚠️  Skipped: $skipped_count files\n";
echo "❌ Failed: $failed_count files\n";
echo "\n📝 Backup files created with .backup extension\n";
echo "🔍 Please test the updated pages and remove .backup files when satisfied\n";
?>
