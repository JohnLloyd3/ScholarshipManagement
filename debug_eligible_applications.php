<?php
require_once __DIR__ . '/config/db.php';

$pdo = getPDO();

echo "=== DEBUG: Eligible Applications ===\n\n";

// Check if there are any approved applications
echo "1. All approved applications:\n";
$stmt = $pdo->query("
    SELECT a.id, a.user_id, a.status, u.first_name, u.last_name, s.title as scholarship_title
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.status = 'approved'
    ORDER BY a.id
");
$approved = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($approved)) {
    echo "   No approved applications found!\n";
} else {
    foreach ($approved as $app) {
        echo "   ID: {$app['id']}, User: {$app['first_name']} {$app['last_name']}, Scholarship: {$app['scholarship_title']}\n";
    }
}

echo "\n2. All disbursements:\n";
$stmt = $pdo->query("
    SELECT d.id, d.user_id, d.scholarship_id, u.first_name, u.last_name, s.title as scholarship_title, d.deleted_at
    FROM disbursements d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN scholarships s ON d.scholarship_id = s.id
    ORDER BY d.id
");
$disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($disbursements)) {
    echo "   No disbursements found!\n";
} else {
    foreach ($disbursements as $disb) {
        $deleted = $disb['deleted_at'] ? " (DELETED)" : "";
        echo "   ID: {$disb['id']}, User: {$disb['first_name']} {$disb['last_name']}, Scholarship: {$disb['scholarship_title']}{$deleted}\n";
    }
}

echo "\n3. Testing getEligibleApplications query:\n";
$stmt = $pdo->query("
    SELECT a.id, a.user_id, s.amount AS application_amount,
           u.first_name, u.last_name, u.student_id,
           s.title AS scholarship_title,
           a.scholarship_id,
           a.reviewed_at
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.status = 'approved'
    AND NOT EXISTS (
        SELECT 1 FROM disbursements d 
        WHERE d.user_id = a.user_id 
        AND d.scholarship_id = a.scholarship_id
        AND (d.deleted_at IS NULL OR d.deleted_at = '0000-00-00 00:00:00')
    )
    ORDER BY u.last_name ASC, u.first_name ASC
");
$eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($eligible)) {
    echo "   No eligible applications found!\n";
} else {
    foreach ($eligible as $app) {
        echo "   ID: {$app['id']}, User: {$app['first_name']} {$app['last_name']}, Scholarship: {$app['scholarship_title']}, Amount: {$app['application_amount']}\n";
    }
}

echo "\n=== END DEBUG ===\n";