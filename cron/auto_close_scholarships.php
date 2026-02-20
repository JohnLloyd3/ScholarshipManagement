<?php
// Auto-close scholarships when deadline or max scholars reached
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

// Close scholarships past deadline
$now = date('Y-m-d');
$stmt = $pdo->prepare('UPDATE scholarships SET status = "closed" WHERE status = "open" AND auto_close = 1 AND deadline IS NOT NULL AND deadline < :now');
$stmt->execute([':now' => $now]);

// Close scholarships that reached max scholars
$sql = 'SELECT s.id, s.max_scholars, COUNT(a.id) AS app_count
        FROM scholarships s
        LEFT JOIN applications a ON a.scholarship_id = s.id AND a.status IN ("approved", "submitted", "under_review")
        WHERE s.status = "open" AND s.auto_close = 1 AND s.max_scholars IS NOT NULL
        GROUP BY s.id, s.max_scholars
        HAVING app_count >= s.max_scholars';
foreach ($pdo->query($sql) as $row) {
    $pdo->prepare('UPDATE scholarships SET status = "closed" WHERE id = :id')->execute([':id' => $row['id']]);
}
echo "Auto-close check complete.\n";
