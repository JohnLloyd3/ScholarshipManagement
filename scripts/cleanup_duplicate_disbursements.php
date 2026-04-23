<?php
/**
 * Cleanup Duplicate Disbursements
 * This script removes duplicate disbursement records, keeping only the latest one per person
 */

require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();

echo "=== Cleanup Duplicate Disbursements ===\n\n";

try {
    // Find all users with multiple disbursements
    $stmt = $pdo->query("
        SELECT user_id, COUNT(*) as count
        FROM disbursements
        WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
        GROUP BY user_id
        HAVING count > 1
    ");
    
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✓ No duplicate disbursements found!\n";
        exit;
    }
    
    echo "Found " . count($duplicates) . " users with duplicate disbursements:\n\n";
    
    $totalDeleted = 0;
    
    foreach ($duplicates as $dup) {
        $userId = $dup['user_id'];
        $count = $dup['count'];
        
        // Get user info
        $userStmt = $pdo->prepare("SELECT first_name, last_name, student_id FROM users WHERE id = :id");
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "User: " . ($user['first_name'] ?? '') . " " . ($user['last_name'] ?? '') . " (ID: " . ($user['student_id'] ?? $userId) . ") - {$count} records\n";
        
        // Get all disbursements for this user, ordered by created_at DESC (keep the latest)
        $disbStmt = $pdo->prepare("
            SELECT id, amount, disbursement_date, status, created_at
            FROM disbursements
            WHERE user_id = :user_id
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ORDER BY created_at DESC
        ");
        $disbStmt->execute([':user_id' => $userId]);
        $disbursements = $disbStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Keep the first one (latest), delete the rest
        $keep = array_shift($disbursements);
        echo "  ✓ Keeping: ID {$keep['id']} - ₱" . number_format($keep['amount'], 2) . " ({$keep['status']}) - " . $keep['created_at'] . "\n";
        
        foreach ($disbursements as $disb) {
            echo "  ✗ Deleting: ID {$disb['id']} - ₱" . number_format($disb['amount'], 2) . " ({$disb['status']}) - " . $disb['created_at'] . "\n";
            
            // Soft delete
            $deleteStmt = $pdo->prepare("UPDATE disbursements SET deleted_at = NOW() WHERE id = :id");
            $deleteStmt->execute([':id' => $disb['id']]);
            $totalDeleted++;
        }
        
        echo "\n";
    }
    
    echo "=== Cleanup Complete ===\n";
    echo "Total duplicate records removed: {$totalDeleted}\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
