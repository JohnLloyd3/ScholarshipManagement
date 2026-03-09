<?php
/**
 * Calculate and update file hashes for existing documents
 * This enables fraud detection for duplicate documents
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/FraudDetectionHelper.php';

echo "=== Calculating Document Hashes ===\n\n";

try {
    $pdo = getPDO();
    
    // Get all documents without hashes
    $stmt = $pdo->query('SELECT id, file_path FROM documents WHERE file_hash IS NULL OR file_hash = ""');
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($documents) . " documents to process.\n\n";
    
    $updated = 0;
    $errors = 0;
    $missing = 0;
    
    $updateStmt = $pdo->prepare('UPDATE documents SET file_hash = :hash WHERE id = :id');
    
    foreach ($documents as $doc) {
        $filePath = __DIR__ . '/../' . $doc['file_path'];
        
        if (!file_exists($filePath)) {
            echo "⚠ File not found: {$doc['file_path']}\n";
            $missing++;
            continue;
        }
        
        try {
            $hash = calculateFileHash($filePath);
            
            if ($hash) {
                $updateStmt->execute([
                    ':hash' => $hash,
                    ':id' => $doc['id']
                ]);
                $updated++;
                echo "✓ Updated document ID {$doc['id']}: " . substr($hash, 0, 16) . "...\n";
            } else {
                echo "✗ Failed to calculate hash for document ID {$doc['id']}\n";
                $errors++;
            }
        } catch (Exception $e) {
            echo "✗ Error processing document ID {$doc['id']}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "✓ Updated: $updated\n";
    echo "⚠ Missing files: $missing\n";
    echo "✗ Errors: $errors\n";
    
    echo "\n✅ Hash calculation completed!\n";
    
} catch (Exception $e) {
    echo "\n❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
