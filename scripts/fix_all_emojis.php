<?php
/**
 * EMOJI REPLACEMENT SCRIPT
 * Replaces all emojis with Font Awesome icons across the entire system
 */

// Emoji to Font Awesome mapping
$emoji_map = [
    // Brand/Logo
    '🎓' => '<i class="fas fa-graduation-cap"></i>',
    '&#127891;' => '<i class="fas fa-graduation-cap"></i>',
    
    // Navigation icons
    '📊' => '<i class="fas fa-chart-line"></i>',
    '📝' => '<i class="fas fa-file-alt"></i>',
    '🔔' => '<i class="fas fa-bell"></i>',
    '📢' => '<i class="fas fa-bullhorn"></i>',
    '📅' => '<i class="fas fa-calendar-alt"></i>',
    '💰' => '<i class="fas fa-money-bill-wave"></i>',
    '⭐' => '<i class="fas fa-star"></i>',
    '👤' => '<i class="fas fa-user"></i>',
    '🚪' => '<i class="fas fa-sign-out-alt"></i>',
    '👥' => '<i class="fas fa-users"></i>',
    '⚙️' => '<i class="fas fa-cog"></i>',
    '📈' => '<i class="fas fa-chart-bar"></i>',
    '🗓️' => '<i class="fas fa-calendar-check"></i>',
    '📄' => '<i class="fas fa-file"></i>',
    '⏳' => '<i class="fas fa-hourglass-half"></i>',
    
    // Form icons (HTML entities)
    '&#128196;' => '<i class="fas fa-id-card"></i>',
    '&#128100;' => '<i class="fas fa-user"></i>',
    '&#9993;' => '<i class="fas fa-envelope"></i>',
    '&#128222;' => '<i class="fas fa-phone"></i>',
    '&#128274;' => '<i class="fas fa-lock"></i>',
    '&#128065;' => '<i class="fas fa-eye"></i>',
    '&#128064;' => '<i class="fas fa-eye-slash"></i>',
    '&#128269;' => '<i class="fas fa-search"></i>',
    '&#128276;' => '<i class="fas fa-bell"></i>',
    
    // Action icons
    '📧' => '<i class="fas fa-envelope"></i>',
    '💾' => '<i class="fas fa-save"></i>',
    '🧪' => '<i class="fas fa-flask"></i>',
    '📤' => '<i class="fas fa-paper-plane"></i>',
    '📖' => '<i class="fas fa-book"></i>',
    '🗄️' => '<i class="fas fa-archive"></i>',
    '👑' => '<i class="fas fa-crown"></i>',
    '📷' => '<i class="fas fa-camera"></i>',
    '🔒' => '<i class="fas fa-lock"></i>',
    '📋' => '<i class="fas fa-clipboard-list"></i>',
    '➕' => '<i class="fas fa-plus"></i>',
    '🔗' => '<i class="fas fa-link"></i>',
    '👁️' => '<i class="fas fa-eye"></i>',
    '✏️' => '<i class="fas fa-edit"></i>',
    '🗑️' => '<i class="fas fa-trash"></i>',
    '⚠️' => '<i class="fas fa-exclamation-triangle"></i>',
    '❌' => '<i class="fas fa-times"></i>',
    '🔍' => '<i class="fas fa-search"></i>',
];

// Directories to scan
$directories = [
    __DIR__ . '/../admin',
    __DIR__ . '/../staff',
    __DIR__ . '/../students',
    __DIR__ . '/../auth',
    __DIR__ . '/../includes',
];

$total_files = 0;
$updated_files = 0;
$total_replacements = 0;

echo "Starting emoji replacement...\n\n";

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $files = glob($dir . '/*.php');
    
    foreach ($files as $file) {
        $total_files++;
        $content = file_get_contents($file);
        $original_content = $content;
        $file_replacements = 0;
        
        // Replace each emoji
        foreach ($emoji_map as $emoji => $icon) {
            $count = 0;
            $content = str_replace($emoji, $icon, $content, $count);
            $file_replacements += $count;
        }
        
        // If changes were made, save the file
        if ($content !== $original_content) {
            // Create backup
            copy($file, $file . '.emoji_backup');
            
            // Save updated content
            file_put_contents($file, $content);
            
            $updated_files++;
            $total_replacements += $file_replacements;
            
            echo "✓ Updated: " . basename($file) . " ($file_replacements replacements)\n";
        }
    }
}

echo "\n";
echo "========================================\n";
echo "EMOJI REPLACEMENT COMPLETE\n";
echo "========================================\n";
echo "Total files scanned: $total_files\n";
echo "Files updated: $updated_files\n";
echo "Total replacements: $total_replacements\n";
echo "\n";
echo "Backup files created with .emoji_backup extension\n";
echo "Test the system and remove backups when satisfied\n";
?>
