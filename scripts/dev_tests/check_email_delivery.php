<?php
require_once __DIR__ . '/../../config/db.php';

$email = $argv[1] ?? null;
if (!$email) {
    echo "Usage: php check_email_delivery.php recipient@example.com\n";
    exit(1);
}

try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Checking email_logs (last 10)\n";
$stmt = $pdo->prepare('SELECT id, user_id, email, subject, status, attempts, created_at FROM email_logs ORDER BY created_at DESC LIMIT 10');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "[{$r['created_at']}] id={$r['id']} email={$r['email']} status={$r['status']} attempts={$r['attempts']} subject={$r['subject']} user_id={$r['user_id']}\n";
}

// Also check for any errors in php error log? Attempt to read Apache/PHP error log if exists
$possibleLogs = [
    __DIR__ . '/../../php_error.log',
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error_log',
];
foreach ($possibleLogs as $p) {
    if (file_exists($p)) {
        echo "\nFound log: {$p}, showing last 50 lines:\n";
        $lines = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $last = array_slice($lines, -50);
        foreach ($last as $l) echo $l . "\n";
        break;
    }
}
