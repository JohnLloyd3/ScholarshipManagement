<?php
require_once __DIR__ . '/../auth/helpers.php';
require_role(['staff','admin']);
require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();

$dataset = $_GET['dataset'] ?? 'applications';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

function send_csv($filename, $headers, $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($dataset === 'applications') {
        $sql = 'SELECT a.id, u.first_name, u.last_name, u.email, s.title as scholarship_title, a.status, a.created_at, (SELECT ROUND(AVG(score),1) FROM reviews r WHERE r.application_id = a.id) as avg_score FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN scholarships s ON a.scholarship_id = s.id';
        $conds = [];
        $params = [];
        if ($from) { $conds[] = 'a.created_at >= :from'; $params[':from']=$from.' 00:00:00'; }
        if ($to) { $conds[] = 'a.created_at <= :to'; $params[':to']=$to.' 23:59:59'; }
        if ($conds) $sql .= ' WHERE '.implode(' AND ', $conds);
        $sql .= ' ORDER BY a.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [ $r['id'], $r['first_name'].' '.$r['last_name'], $r['email'], $r['scholarship_title'], $r['status'], $r['created_at'], $r['avg_score'] ];
        }
        send_csv('applications_export.csv', ['id','applicant','email','scholarship','status','submitted_at','avg_score'],$rows);
    }

    if ($dataset === 'scholarships') {
        $sql = 'SELECT id, title, organization, status, amount, deadline, created_at FROM scholarships ORDER BY created_at DESC';
        $params = [];
        if ($from || $to) {
            $conds = [];
            if ($from) { $conds[] = 'created_at >= :from'; $params[':from']=$from.' 00:00:00'; }
            if ($to) { $conds[] = 'created_at <= :to'; $params[':to']=$to.' 23:59:59'; }
            $sql = 'SELECT id, title, organization, status, amount, deadline, created_at FROM scholarships WHERE '.implode(' AND ', $conds).' ORDER BY created_at DESC';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = [$r['id'],$r['title'],$r['organization'],$r['status'],$r['amount'],$r['deadline'],$r['created_at']];
        send_csv('scholarships_export.csv',['id','title','organization','status','amount','deadline','created_at'],$rows);
    }

    if ($dataset === 'users') {
        $sql = 'SELECT id, first_name, last_name, email, username, role, created_at FROM users ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[] = [$r['id'],$r['first_name'].' '.$r['last_name'],$r['email'],$r['username'],$r['role'],$r['created_at']];
        send_csv('users_export.csv',['id','name','email','username','role','created_at'],$rows);
    }
}

?>
<?php
$page_title = 'Reports & Exports - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📊 Reports & Exports</h1>
  <p class="text-muted">Generate and download data reports</p>
</div>

<div class="content-card">
  <form method="get" style="display:flex;gap:var(--space-md);align-items:end;flex-wrap:wrap;margin-bottom:var(--space-xl)">
    <div class="form-group" style="margin:0">
      <label class="form-label">Dataset</label>
      <select name="dataset" class="form-select">
        <option value="applications" <?= $dataset==='applications'?'selected':''?>>Applications</option>
        <option value="scholarships" <?= $dataset==='scholarships'?'selected':''?>>Scholarships</option>
        <option value="users" <?= $dataset==='users'?'selected':''?>>Users</option>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-input">
    </div>
    <div class="form-group" style="margin:0">
      <label class="form-label">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-input">
    </div>
    <button class="btn btn-primary" name="export" value="csv">📥 Export CSV</button>
  </form>
  <p class="text-muted">CSV export contains recent rows for the selected dataset. Use filters for date ranges on applications and scholarships.</p>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
