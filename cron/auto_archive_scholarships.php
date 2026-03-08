<?php
// Run this from CLI or scheduled task. Moves expired open scholarships to scholarship_archive and closes them.
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM scholarships WHERE status = 'open' AND deadline IS NOT NULL AND deadline < CURDATE()");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ins = $pdo->prepare('INSERT INTO scholarship_archive (scholarship_id, title, description, organization, amount, deadline, archived_at, archived_by, original_status) VALUES (:sid, :title, :desc, :org, :amt, :deadline, NOW(), NULL, :orig)');
    $upd = $pdo->prepare("UPDATE scholarships SET status = 'closed', updated_at = NOW() WHERE id = :id");
    foreach ($rows as $r) {
        $ins->execute([
            ':sid' => $r['id'],
            ':title' => $r['title'],
            ':desc' => $r['description'],
            ':org' => $r['organization'],
            ':amt' => $r['amount'] ?? 0,
            ':deadline' => $r['deadline'],
            ':orig' => $r['status'] ?? 'open'
        ]);
        $upd->execute([':id' => $r['id']]);
    }
    $pdo->commit();
    echo "Archived " . count($rows) . " scholarships.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
