<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

startSecureSession();
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$csrf_token = generateCSRFToken();

// Filters
$filterUser   = trim($_GET['user']   ?? '');
$filterAction = trim($_GET['action'] ?? '');
$filterEntity = trim($_GET['entity'] ?? '');
$filterFrom   = trim($_GET['date_from'] ?? '');
$filterTo     = trim($_GET['date_to']   ?? '');

// Build query
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
if ($filterEntity) {
    $where[] = 'COALESCE(al.target_table, al.entity_type) LIKE :entity';
    $params[':entity'] = '%' . $filterEntity . '%';
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
$total = 0;
try {
    $pdo->query('SELECT 1 FROM audit_logs LIMIT 1'); // check table exists
    $stmt = $pdo->prepare('SELECT al.*, u.username, u.first_name, u.last_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY al.created_at DESC LIMIT 200');
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($logs);
} catch (Exception $e) {
    $logs = [];
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Action', 'Entity', 'Entity ID', 'Description', 'IP', 'Date']);
    foreach ($logs as $l) {
        fputcsv($out, [
            $l['id'],
            ($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '') . ' (' . ($l['username'] ?? 'system') . ')',
            $l['action'],
            $l['target_table'] ?? $l['entity_type'] ?? '',
            $l['target_id'] ?? $l['entity_id'] ?? '',
            $l['description'] ?? $l['new_value'] ?? '',
            $l['ip'] ?? $l['ip_address'] ?? '',
            $l['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$page_title = 'Audit Logs - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Audit Logs</h1>
  <p class="text-muted">Every action in the system is recorded here</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="content-card" style="margin-bottom:var(--space-lg);">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:var(--space-md);align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>User</label>
      <input type="text" name="user" class="form-input" placeholder="Name or username" value="<?= htmlspecialchars($filterUser) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Action</label>
      <input type="text" name="action" class="form-input" placeholder="e.g. approve, delete" value="<?= htmlspecialchars($filterAction) ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Entity</label>
      <input type="text" name="entity" class="form-input" placeholder="e.g. applications" value="<?= htmlspecialchars($filterEntity) ?>">
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
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-ghost">📥 Export CSV</a>
  </form>
</div>

<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);">
    <h2>Log Entries <small class="text-muted">(<?= $total ?> shown)</small></h2>
  </div>

  <?php if (!empty($logs)): ?>
    <table class="modern-table">
      <thead>
        <tr><th>#</th><th>User</th><th>Action</th><th>Entity</th><th>ID</th><th>Description</th><th>IP</th><th>Date</th></tr>
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
            <td><small><?= htmlspecialchars($l['target_id'] ?? $l['entity_id'] ?? '—') ?></small></td>
            <td style="max-width:300px;"><small><?= htmlspecialchars(mb_strimwidth($l['description'] ?? $l['new_value'] ?? '', 0, 100, '…')) ?></small></td>
            <td><small><?= htmlspecialchars($l['ip'] ?? $l['ip_address'] ?? '—') ?></small></td>
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
