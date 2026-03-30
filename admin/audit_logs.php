<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();

// Ensure audit_logs table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        action VARCHAR(255) NOT NULL,
        entity_type VARCHAR(128) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        old_value TEXT DEFAULT NULL,
        new_value TEXT DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_entity (entity_type, entity_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$qUser = trim($_GET['user'] ?? '');
$qAction = trim($_GET['action'] ?? '');
$qEntity = trim($_GET['entity'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$params = [];
$where = [];
if ($qUser !== '') { $where[] = 'a.user_id = :uid'; $params[':uid'] = (int)$qUser; }
if ($qAction !== '') { $where[] = 'a.action LIKE :action'; $params[':action'] = '%' . $qAction . '%'; }
if ($qEntity !== '') { $where[] = 'a.entity_type = :etype'; $params[':etype'] = $qEntity; }
if ($from !== '') { $where[] = 'a.created_at >= :from'; $params[':from'] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'a.created_at <= :to'; $params[':to'] = $to . ' 23:59:59'; }

$sql = 'SELECT a.*, u.first_name, u.last_name, u.email, u.role FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.created_at DESC LIMIT 1000';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV export
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','User','Role','Action','Entity Type','Entity ID','IP','Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], 
            ($r['first_name'] ? $r['first_name'].' '.$r['last_name'] : 'User #'.$r['user_id']), 
            $r['role'] ?? '',
            $r['action'], 
            $r['entity_type'], 
            $r['entity_id'], 
            $r['ip'], 
            $r['created_at']
        ]);
    }
    fclose($out);
    exit;
}

// Get statistics
$stats = [];
$stats['total_logs'] = $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$stats['today_logs'] = $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
$stats['unique_users'] = $pdo->query('SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE user_id IS NOT NULL')->fetchColumn();

// Top actions
$topActions = $pdo->query('SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action ORDER BY count DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$page_title = 'Audit Logs - Admin';
$base_path = '../';
$extra_css = '
  .stats-mini { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-md); margin-bottom: var(--space-xl); }
  .stat-mini { background: var(--white); padding: var(--space-md); border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-sm); }
  .stat-mini-value { font-size: 1.5rem; font-weight: 700; color: var(--red-primary); }
  .stat-mini-label { color: var(--gray-600); margin-top: var(--space-xs); font-size: 0.75rem; }
';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Audit & Activity Logs</h1>
  <p class="text-muted">Complete system activity tracking and security monitoring</p>
</div>

<div class="stats-mini">
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['total_logs']) ?></div>
    <div class="stat-mini-label">Total Logs</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['today_logs']) ?></div>
    <div class="stat-mini-label">Today's Activity</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['unique_users']) ?></div>
    <div class="stat-mini-label">Active Users</div>
  </div>
</div>

<div class="content-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
    <h3>Activity Logs</h3>
    <a class="btn btn-secondary btn-sm" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">📥 Export CSV</a>
  </div>

  <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--space-md);margin-bottom:var(--space-xl)">
    <input name="user" value="<?= htmlspecialchars($qUser) ?>" placeholder="User ID" class="form-input">
    <input name="action" value="<?= htmlspecialchars($qAction) ?>" placeholder="Action" class="form-input">
    <input name="entity" value="<?= htmlspecialchars($qEntity) ?>" placeholder="Entity type" class="form-input">
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" placeholder="From" class="form-input">
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" placeholder="To" class="form-input">
    <button class="btn btn-primary">🔍 Filter</button>
  </form>

  <?php if (empty($rows)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3 class="empty-state-title">No Audit Logs</h3>
      <p class="empty-state-description">No activity logs match your filters</p>
    </div>
  <?php else: ?>
    <div style="overflow-x: auto;">
      <table class="modern-table">
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Entity ID</th>
            <th>IP Address</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td>
                <?php if ($r['first_name']): ?>
                  <strong><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></strong><br>
                  <small class="text-muted"><?= htmlspecialchars($r['email']) ?></small><br>
                  <span class="status-badge status-<?= strtolower($r['role']) ?>"><?= htmlspecialchars($r['role']) ?></span>
                <?php else: ?>
                  <span class="text-muted">User #<?= (int)$r['user_id'] ?></span>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($r['action']) ?></strong></td>
              <td><?= htmlspecialchars($r['entity_type'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['entity_id'] ?? '—') ?></td>
              <td><small><?= htmlspecialchars($r['ip'] ?? '—') ?></small></td>
              <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($topActions)): ?>
<div class="content-card" style="margin-top: var(--space-xl);">
  <h3 style="margin-bottom: var(--space-lg);">Top Actions</h3>
  <table class="modern-table">
    <thead>
      <tr>
        <th>Action</th>
        <th>Count</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($topActions as $ta): ?>
        <tr>
          <td><?= htmlspecialchars($ta['action']) ?></td>
          <td><strong><?= number_format($ta['count']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
