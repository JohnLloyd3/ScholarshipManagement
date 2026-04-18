<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

startSecureSession();
requireLogin();
requireAnyRole(['admin', 'staff'], 'Staff access required');

$pdo = getPDO();

// Filters
$filterUser   = trim($_GET['user']      ?? '');
$filterAction = trim($_GET['action']    ?? '');
$filterFrom   = trim($_GET['date_from'] ?? '');
$filterTo     = trim($_GET['date_to']   ?? '');

$where  = ['1=1'];
$params = [];

if ($filterUser) {
    $where[] = '(u.username LIKE :user OR CONCAT(u.first_name," ",u.last_name) LIKE :user2)';
    $params[':user']  = '%' . $filterUser . '%';
    $params[':user2'] = '%' . $filterUser . '%';
}
if ($filterAction) {
    $where[] = 'al.action LIKE :action';
    $params[':action'] = '%' . $filterAction . '%';
}
if ($filterFrom) {
    $where[] = 'al.created_at >= :date_from';
    $params[':date_from'] = $filterFrom . ' 00:00:00';
}
if ($filterTo) {
    $where[] = 'al.created_at <= :date_to';
    $params[':date_to'] = $filterTo . ' 23:59:59';
}

$logs = [];
try {
    $pdo->query('SELECT 1 FROM audit_logs LIMIT 1');
    $stmt = $pdo->prepare('SELECT al.*, u.username, u.first_name, u.last_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY al.created_at DESC LIMIT 100');
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
}

$page_title = 'Audit Logs - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Audit Logs</h1>
</div>

<!-- Filters -->
<div class="content-card" style="margin-bottom:var(--space-lg);">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:var(--space-md);align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>User</label>
      <input type="text" name="user" class="form-input" placeholder="Name or username" value="<?= htmlspecialchars($filterUser) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Action</label>
      <input type="text" name="action" class="form-input" placeholder="e.g. approve" value="<?= htmlspecialchars($filterAction) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Date From</label>
      <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($filterFrom) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Date To</label>
      <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($filterTo) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="audit_logs.php" class="btn btn-ghost">Clear</a>
  </form>
</div>

<div class="content-card">
  <h2>Log Entries <small class="text-muted">(<?= count($logs) ?> shown)</small></h2>
  <?php if (!empty($logs)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead>
        <tr><th>#</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><small><?= (int)$l['id'] ?></small></td>
            <td>
              <?php if ($l['username']): ?>
                <strong><?= htmlspecialchars(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($l['username']) ?></small>
              <?php else: ?>
                <span class="text-muted">System</span>
              <?php endif; ?>
            </td>
            <td><span class="status-badge"><?= htmlspecialchars($l['action']) ?></span></td>
            <td><small><?= htmlspecialchars($l['target_table'] ?? $l['entity_type'] ?? '—') ?></small></td>
            <td style="max-width:300px;"><small><?= htmlspecialchars(mb_strimwidth($l['description'] ?? $l['new_value'] ?? '', 0, 100, '…')) ?></small></td>
            <td><small><?= date('M d, Y H:i', strtotime($l['created_at'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3 class="empty-state-title">No Logs Found</h3>
      <p class="empty-state-description">No audit log entries match your filters.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
