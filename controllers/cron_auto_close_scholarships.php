<?php
// Script to automatically close scholarships after deadline
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

$stmt = $pdo->prepare("UPDATE scholarships SET status = 'closed' WHERE status = 'open' AND deadline IS NOT NULL AND deadline < CURDATE()");
$stmt->execute();

echo "Scholarship status auto-update complete.\n";
