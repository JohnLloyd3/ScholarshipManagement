<?php
/**
 * Database Migration: Add profile_picture column to users table
 * Run this script once to add the profile picture functionality
 */

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getPDO();
    
    echo "Starting migration: Add profile_picture column...\n";
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Column 'profile_picture' already exists. Skipping.\n";
    } else {
        // Add profile_picture column
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
        echo "✓ Added 'profile_picture' column to users table.\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nProfile picture feature is now ready to use.\n";
    echo "Users can upload profile pictures from their profile pages.\n";
    
} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your database connection and try again.\n";
    exit(1);
}
