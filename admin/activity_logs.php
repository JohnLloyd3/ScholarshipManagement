<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');
$pdo = getPDO();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
$limit = min(max($limit, 10), 1000);

$stmt = $pdo->prepare('SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT :lim');
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$page_title = 'Activity Logs - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 System Activity Log</h1>
  <p class="text-muted">Recent system actions and audit trail</p>
</div>

<div class="content-card">
  <form method="get" style="margin-bottom:var(--space-xl);display:flex;gap:var(--space-md);align-items:end">
    <div class="form-group" style="margin:0">
      <label class="form-label">Limit</label>
      <input type="number" name="limit" value="<?= htmlspecialchars($limit) ?>" class="form-input" style="width:120px">
    </div>
    <button class="btn btn-primary">Apply</button>
  </form>
  
  <table class="modern-table">
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>Details</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><small><?= htmlspecialchars($l['created_at']) ?></small></td>
          <td><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
          <td><?= htmlspecialchars($l['action']) ?></td>
          <td><?= htmlspecialchars($l['entity_type'] ?? $l['target_table'] ?? '') ?></td>
          <td><?= htmlspecialchars($l['entity_id'] ?? $l['target_id'] ?? '') ?></td>
          <td><pre style="white-space:pre-wrap;max-width:600px;overflow:auto;background:var(--gray-50);padding:var(--space-sm);border-radius:var(--radius-md);font-size:0.875rem"><?= htmlspecialchars($l['new_values'] ?? $l['description'] ?? '') ?></pre></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
