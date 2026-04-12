<?php
startSecureSession();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

$pdo = getPDO();
$script = isset($_GET['script']) ? basename($_GET['script']) : '';
if (!$script) {
    header('Location: cron.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM cron_runs WHERE script = :script ORDER BY ran_at DESC LIMIT 200');
$stmt->execute([':script'=>$script]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$page_title = 'Cron Logs - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📜 Cron Logs: <?= htmlspecialchars($script) ?></h1>
  <p class="text-muted">Execution history for automated tasks</p>
</div>

<div class="content-card">
  <a href="cron.php" class="btn btn-secondary" style="margin-bottom:var(--space-xl)">← Back to Cron Jobs</a>
  
  <table class="modern-table">
    <thead><tr><th>Ran At</th><th>Status</th><th>Output</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['ran_at']) ?></td>
        <td><span class="status-badge status-<?= strtolower($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><pre style="white-space:pre-wrap;max-height:240px;overflow:auto;background:var(--gray-50);padding:var(--space-md);border-radius:var(--radius-md);font-size:0.875rem"><?= htmlspecialchars($r['output']) ?></pre></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
