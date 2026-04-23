<?php
/**
 * CLEANUP EMOJI BACKUPS
 * Removes all .emoji_backup files created by the emoji replacement script
 */

$directories = [
    __DIR__ . '/../admin',
    __DIR__ . '/../staff',
    __DIR__ . '/../students',
    __DIR__ . '/../auth',
    __DIR__ . '/../includes',
];

$total_deleted = 0;

echo "Cleaning up emoji backup files...\n\n";

foreach ($directories as $dir) {
    if (!is_dir($dir)) continue;
    
    $backups = glob($dir . '/*.emoji_backup');
    
    foreach ($backups as $backup) {
        if (unlink($backup)) {
            $total_deleted++;
            echo "✓ Deleted: " . basename($backup) . "\n";
        } else {
            echo "✗ Failed to delete: " . basename($backup) . "\n";
        }
    }
}

echo "\n";
echo "========================================\n";
echo "CLEANUP COMPLETE\n";
echo "========================================\n";
echo "Total backup files deleted: $total_deleted\n";
?>
