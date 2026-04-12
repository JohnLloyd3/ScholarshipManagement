<?php
startSecureSession();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

$pdo = getPDO();

$dataset = $_GET['dataset'] ?? 'applications';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

function get_report_data($pdo, $dataset, $from, $to) {
    if ($dataset === 'applications') {
        $sql = 'SELECT a.id, u.first_name, u.last_name, u.email, s.title as scholarship_title, a.status, a.submitted_at, a.created_at
                FROM applications a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN scholarships s ON a.scholarship_id = s.id';
        $conds = []; $params = [];
        if ($from) { $conds[] = 'a.created_at >= :from'; $params[':from']=$from.' 00:00:00'; }
        if ($to)   { $conds[] = 'a.created_at <= :to';   $params[':to']=$to.' 23:59:59'; }
        if ($conds) $sql .= ' WHERE '.implode(' AND ', $conds);
        $sql .= ' ORDER BY a.created_at DESC';
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $headers = ['ID','Applicant','Email','Scholarship','Status','Submitted'];
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)
            $rows[] = [$r['id'], $r['first_name'].' '.$r['last_name'], $r['email'], $r['scholarship_title'], $r['status'], $r['submitted_at'] ?? $r['created_at']];
        return [$headers, $rows];
    }
    if ($dataset === 'scholarships') {
        $sql = 'SELECT id, title, organization, status, amount, deadline, created_at FROM scholarships';
        $conds = []; $params = [];
        if ($from) { $conds[] = 'created_at >= :from'; $params[':from']=$from.' 00:00:00'; }
        if ($to)   { $conds[] = 'created_at <= :to';   $params[':to']=$to.' 23:59:59'; }
        if ($conds) $sql .= ' WHERE '.implode(' AND ', $conds);
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $headers = ['ID','Title','Organization','Status','Amount','Deadline','Created'];
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)
            $rows[] = [$r['id'],$r['title'],$r['organization'],$r['status'],$r['amount'],$r['deadline'],$r['created_at']];
        return [$headers, $rows];
    }
    if ($dataset === 'users') {
        $stmt = $pdo->query('SELECT id, first_name, last_name, email, username, role, created_at FROM users ORDER BY created_at DESC');
        $headers = ['ID','Name','Email','Username','Role','Created'];
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r)
            $rows[] = [$r['id'],$r['first_name'].' '.$r['last_name'],$r['email'],$r['username'],$r['role'],$r['created_at']];
        return [$headers, $rows];
    }
    return [[], []];
}

$export = $_GET['export'] ?? '';

if ($export === 'csv') {
    [$headers, $rows] = get_report_data($pdo, $dataset, $from, $to);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$dataset.'_export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

if ($export === 'xlsx') {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) { die('PhpSpreadsheet not installed. Run: composer install'); }
    require_once $autoload;
    [$headers, $rows] = get_report_data($pdo, $dataset, $from, $to);
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_merge([$headers], $rows), null, 'A1');
    // Bold header row
    $sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')->getFont()->setBold(true);
    foreach (range(1, count($headers)) as $col)
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$dataset.'_export.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if ($export === 'pdf') {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) { die('Dompdf not installed. Run: composer install'); }
    require_once $autoload;
    [$headers, $rows] = get_report_data($pdo, $dataset, $from, $to);
    $html = '<style>body{font-family:sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th{background:#c41e3a;color:#fff;padding:6px 8px;text-align:left}td{padding:5px 8px;border-bottom:1px solid #ddd}</style>';
    $html .= '<h2 style="color:#c41e3a">'.ucfirst($dataset).' Report</h2>';
    $html .= '<table><thead><tr>';
    foreach ($headers as $h) $html .= '<th>'.htmlspecialchars($h).'</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>';
        foreach ($r as $cell) $html .= '<td>'.htmlspecialchars((string)$cell).'</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($dataset.'_export.pdf', ['Attachment' => true]);
    exit;
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
    <button class="btn btn-primary" name="export" value="xlsx" style="background:#217346">📊 Export Excel</button>
    <button class="btn btn-primary" name="export" value="pdf" style="background:#c41e3a">📄 Export PDF</button>
  </form>
  <p class="text-muted">CSV export contains recent rows for the selected dataset. Use filters for date ranges on applications and scholarships.</p>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
