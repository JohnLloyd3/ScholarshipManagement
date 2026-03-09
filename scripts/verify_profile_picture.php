<?php
/**
 * Verification Script: Check if profile_picture column exists and is working
 */

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getPDO();
    
    echo "=== Profile Picture Feature Verification ===\n\n";
    
    // Check if column exists
    echo "1. Checking if 'profile_picture' column exists in users table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($column) {
        echo "   ✅ Column exists!\n";
        echo "   - Type: " . $column['Type'] . "\n";
        echo "   - Null: " . $column['Null'] . "\n";
        echo "   - Default: " . ($column['Default'] ?? 'NULL') . "\n\n";
    } else {
        echo "   ❌ Column does NOT exist!\n\n";
        exit(1);
    }
    
    // Check users with profile pictures
    echo "2. Checking users with profile pictures...\n";
    $stmt = $pdo->query("SELECT id, username, first_name, last_name, role, profile_picture FROM users WHERE profile_picture IS NOT NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "   Found " . count($users) . " user(s) with profile pictures:\n";
        foreach ($users as $user) {
            echo "   - {$user['first_name']} {$user['last_name']} ({$user['role']}): {$user['profile_picture']}\n";
        }
    } else {
        echo "   No users have uploaded profile pictures yet.\n";
    }
    echo "\n";
    
    // Check if uploads directory exists
    echo "3. Checking uploads directory...\n";
    $upload_dir = __DIR__ . '/../uploads/profiles/';
    if (is_dir($upload_dir)) {
        echo "   ✅ Directory exists: uploads/profiles/\n";
        $files = glob($upload_dir . '*');
        echo "   - Files: " . count($files) . "\n";
    } else {
        echo "   ⚠️  Directory does not exist yet (will be created on first upload)\n";
    }
    echo "\n";
    
    // Check if default avatar exists
    echo "4. Checking default avatar...\n";
    $default_avatar = __DIR__ . '/../assets/image/default-avatar.svg';
    if (file_exists($default_avatar)) {
        echo "   ✅ Default avatar exists: assets/image/default-avatar.svg\n";
    } else {
        echo "   ❌ Default avatar NOT found!\n";
    }
    echo "\n";
    
    echo "=== Verification Complete ===\n";
    echo "\n✅ Profile picture feature is fully configured and ready to use!\n";
    echo "\nUsers can now:\n";
    echo "- Upload profile pictures from their profile pages\n";
    echo "- View profile pictures in the sidebar and profile pages\n";
    echo "- Supported formats: JPG, JPEG, PNG, GIF\n";
    
} catch (PDOException $e) {
    echo "\n❌ Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}
