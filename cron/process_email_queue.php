<?php
// Email queue processor for Scholarship Management System
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';

$pdo = getPDO();

$stmt = $pdo->prepare('SELECT * FROM email_logs WHERE status = "queued" ORDER BY created_at ASC LIMIT 10');
$stmt->execute();
$emails = $stmt->fetchAll();

foreach ($emails as $email) {
    $success = sendEmail($email['email'], $email['subject'], $email['body'], true);
    $status = $success ? 'sent' : 'failed';
    $update = $pdo->prepare('UPDATE email_logs SET status = :status WHERE id = :id');
    $update->execute([':status' => $status, ':id' => $email['id']]);
    // Optionally, log errors or retry failed emails
}

echo "Processed " . count($emails) . " emails.\n";
