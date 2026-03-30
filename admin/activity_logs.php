<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
requireLogin();
requireRole('admin', 'Admin access required');
$pdo = getPDO();

$limit      = min(max((int)($_GET['limit'] ?? 100), 10), 1000);
$search     = trim($_GET['search'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? '');
$filterEntity = trim($_GET['entity'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]          = '(u.username LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
    $params[':search'] = "%{$search}%";
}
if ($filterAction !== '') {
    $where[]           = 'al.action LIKE :action';
    $params[':action'] = "%{$filterAction}%";
}
if ($filterEntity !== '') {
    $where[]           = 'al.entity_type = :entity';
    $params[':entity'] = $filterEntity;
}

$whereSQL = implode(' AND ', $where);

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE {$whereSQL}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $limit));

// Fetch page
$params[':limit']  = $limit;
$params[':offset'] = $offset;
$stmt = $pdo->prepare("
    SELECT al.*, u.username, u.first_name, u.last_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE {$whereSQL}
    ORDER BY al.created_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, in_array($k, [':limit', ':offset']) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct entity types for filter dropdown
$entities = $pdo->query("SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

// Action color map
function actionBadgeClass(string $action): string {
    if (str_contains($action, 'DELETE') || str_contains($action, 'REJECT'))  return 'badge-danger';
    if (str_contains($action, 'CREATE') || str_contains($action, 'SUBMIT'))  return 'badge-success';
    if (str_contains($action, 'UPDATE') || str_contains($action, 'EDIT') || str_contains($action, 'CHANGE')) return 'badge-warning';
    if (str_contains($action, 'LOGIN') || str_contains($action, 'LOGOUT'))   return 'badge-info';
    if (str_contains($action, 'APPROVE'))                                     return 'badge-primary';
    return 'badge-secondary';
}

function prettyJson(?string $json): string {
    if (!$json) return '—';
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return htmlspecialchars($json);
    $parts = [];
    foreach ($decoded as $k => $v) {
        $val = is_array($v) ? json_encode($v) : (string)$v;
        $parts[] = '<span style="color:var(--gray-500)">' . htmlspecialchars($k) . ':</span> <span>' . htmlspecialchars($val) . '</span>';
    }
    return implode('<br>', $parts);
}

$page_title = 'Activity Logs - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>
<style>
.badge { display:inline-block; padding:0.25rem 0.6rem; border-radius:999px; font-size:0.75rem; font-weight:600; letter-spacing:0.02em; }
.badge-danger    { background:#fee2e2; color:#b91c1c; }
.badge-success   { background:#dcfce7; color:#15803d; }
.badge-warning   { background:#fef9c3; color:#a16207; }
.badge-info      { background:#e0f2fe; color:#0369a1; }
.badge-primary   { background:#ede9fe; color:#6d28d9; }
.badge-secondary { background:var(--gray-100); color:var(--gray-600); }
.log-detail      { font-size:0.8rem; line-height:1.6; color:var(--gray-700); }
.filter-bar      { display:flex; gap:var(--space-md); flex-wrap:wrap; align-items:flex-end; margin-bottom:var(--space-xl); }
.filter-bar .form-group { margin:0; }
.pagination      { display:flex; gap:var(--space-sm); align-items:center; justify-content:flex-end; margin-top:var(--space-xl); flex-wrap:wrap; }
.pagination a, .pagination span { padding:0.4rem 0.75rem; border-radius:var(--radius-md); border:1px solid var(--gray-200); font-size:0.875rem; text-decoration:none; color:var(--gray-700); }
.pagination a:hover { background:var(--red-ghost); color:var(--red-primary); border-color:var(--red-primary); }
.pagination .current { background:var(--red-primary); color:#fff; border-color:var(--red-primary); }
.pagination .disabled { color:var(--gray-400); pointer-events:none; }
</style>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--space-md)">
  <div>
    <h1>📋 Activity Logs</h1>
    <p class="text-muted"><?= number_format($total) ?> total entries</p>
  </div>
  <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
</div>

<?php
// CSV export
if (isset($_GET['export'])) {
    $expStmt = $pdo->prepare("
        SELECT al.created_at, u.username, al.action, al.entity_type, al.entity_id, al.new_values, al.ip_address
        FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id
        WHERE {$whereSQL} ORDER BY al.created_at DESC
    ");
    foreach ($params as $k => $v) {
        if (in_array($k, [':limit', ':offset'])) continue;
        $expStmt->bindValue($k, $v);
    }
    $expStmt->execute();
    $rows = $expStmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time', 'User', 'Action', 'Entity', 'Entity ID', 'Details', 'IP']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'], $r['username'] ?? 'System', $r['action'], $r['entity_type'], $r['entity_id'], $r['new_values'] ?? '', $r['ip_address']]);
    }
    fclose($out);
    exit;
}
?>

<div class="content-card">
  <form method="get" class="filter-bar">
    <div class="form-group">
      <label class="form-label">Search User</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Username or name" class="form-input" style="width:180px">
    </div>
    <div class="form-group">
      <label class="form-label">Action</label>
      <input type="text" name="action_filter" value="<?= htmlspecialchars($filterAction) ?>" placeholder="e.g. DELETE" class="form-input" style="width:160px">
    </div>
    <div class="form-group">
      <label class="form-label">Entity</label>
      <select name="entity" class="form-select" style="width:160px">
        <option value="">All</option>
        <?php foreach ($entities as $e): ?>
          <option value="<?= htmlspecialchars($e) ?>" <?= $filterEntity === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Per page</label>
      <select name="limit" class="form-select" style="width:100px">
        <?php foreach ([50, 100, 200, 500] as $n): ?>
          <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary">Filter</button>
    <a href="activity_logs.php" class="btn btn-ghost">Reset</a>
  </form>

  <?php if (empty($logs)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3 class="empty-state-title">No Logs Found</h3>
      <p class="empty-state-description">Try adjusting your filters.</p>
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table class="modern-table">
        <thead>
          <tr>
            <th style="white-space:nowrap">Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Entity</th>
            <th>ID</th>
            <th>Details</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td style="white-space:nowrap;font-size:0.8rem;color:var(--gray-500)">
                <?= date('M d, Y', strtotime($l['created_at'])) ?><br>
                <span style="color:var(--gray-400)"><?= date('H:i:s', strtotime($l['created_at'])) ?></span>
              </td>
              <td>
                <?php if ($l['first_name'] || $l['last_name']): ?>
                  <div style="font-weight:500"><?= htmlspecialchars(trim($l['first_name'] . ' ' . $l['last_name'])) ?></div>
                  <div style="font-size:0.8rem;color:var(--gray-500)"><?= htmlspecialchars($l['username'] ?? '') ?></div>
                <?php else: ?>
                  <span class="text-muted">System</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= actionBadgeClass($l['action']) ?>"><?= htmlspecialchars($l['action']) ?></span></td>
              <td style="font-size:0.875rem"><?= htmlspecialchars($l['entity_type']) ?></td>
              <td style="font-size:0.875rem;color:var(--gray-500)"><?= (int)$l['entity_id'] ?></td>
              <td class="log-detail"><?= prettyJson($l['new_values']) ?></td>
              <td style="font-size:0.75rem;color:var(--gray-400);white-space:nowrap"><?= htmlspecialchars($l['ip_address']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php
        $qs = fn($p) => '?' . http_build_query(array_merge($_GET, ['page' => $p]));
        ?>
        <a href="<?= $qs(1) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">«</a>
        <a href="<?= $qs($page - 1) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
          <?php if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
          <?php else: ?>
            <a href="<?= $qs($i) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <a href="<?= $qs($page + 1) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">›</a>
        <a href="<?= $qs($totalPages) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">»</a>
        <span style="font-size:0.875rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?></span>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
