<?php
require_once __DIR__ . '/../auth/helpers.php';
require_role(['staff','admin']);
require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();

// Ensure audit_logs table exists (safe no-op if already present)
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
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$qUser = trim($_GET['user'] ?? '');
$qEntity = trim($_GET['entity'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$params = [];
$where = [];
if ($qUser !== '') { $where[] = 'user_id = :uid'; $params[':uid'] = (int)$qUser; }
if ($qEntity !== '') { $where[] = 'entity_type = :etype'; $params[':etype'] = $qEntity; }
if ($from !== '') { $where[] = 'created_at >= :from'; $params[':from'] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'created_at <= :to'; $params[':to'] = $to . ' 23:59:59'; }

$sql = 'SELECT a.*, u.first_name, u.last_name, u.email FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.created_at DESC LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV export
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','user','action','entity_type','entity_id','created_at','ip','user_agent']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], 
            ($r['first_name'] ? $r['first_name'].' '.$r['last_name'] : ''), 
            $r['action'], 
            $r['entity_type'] ?? '', 
            $r['entity_id'] ?? '', 
            $r['created_at'], 
            $r['ip'] ?? '', 
            $r['user_agent'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

?>
<?php
$page_title = 'Audit Logs - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Audit / Activity Logs</h1>
  <p class="text-muted">Track system activities and user actions</p>
</div>

<div class="content-card">
  <form method="get" style="display:flex;gap:var(--space-md);flex-wrap:wrap;margin-bottom:var(--space-xl)">
    <input name="user" value="<?= htmlspecialchars($qUser) ?>" placeholder="User ID" class="form-input" style="width:120px">
    <input name="entity" value="<?= htmlspecialchars($qEntity) ?>" placeholder="Entity type" class="form-input" style="flex:1;min-width:200px">
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-input">
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-input">
    <button class="btn btn-primary">🔍 Filter</button>
    <a class="btn btn-secondary" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">📥 Export CSV</a>
  </form>

  <table class="modern-table">
    <thead><tr><th>#</th><th>User</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>When</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['first_name'] ? $r['first_name'].' '.$r['last_name'] : $r['user_id']) ?><br><small class="text-muted"><?= htmlspecialchars($r['email'] ?? '') ?></small></td>
          <td><?= htmlspecialchars($r['action']) ?></td>
          <td><?= htmlspecialchars($r['entity_type'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['entity_id'] ?? '') ?></td>
          <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
          <td><?= htmlspecialchars($r['ip'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
