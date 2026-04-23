<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

$pdo = getPDO();

// Exports
if (!empty($_GET['export']) && $_GET['export'] === 'applications_csv') {
    $filters = [];
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    $data = getApplicationsReport($pdo, $filters);
    exportToCSV($data, 'applications_report.csv');
}

$stats = getDashboardStats($pdo);

?>
<?php
$page_title = 'Analytics Dashboard - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--space-xl);margin-bottom:var(--space-xl)">
  <div class="content-card">
    <h3 style="margin-bottom:var(--space-lg)">Total Applications</h3>
    <div class="stat-value" style="font-size:2.5rem;margin-bottom:var(--space-lg)"><?= (int)$stats['total_applications'] ?></div>
    <canvas id="statusChart" style="max-height:260px"></canvas>
  </div>

  <div class="content-card">
    <h3 style="margin-bottom:var(--space-lg)">Approval Overview</h3>
    <p style="margin-bottom:var(--space-lg)">Approved: <?= (int)$stats['approved_count'] ?> — Rejected: <?= (int)$stats['rejected_count'] ?></p>
    <canvas id="approvalChart" style="max-height:260px"></canvas>
  </div>

  <div class="content-card">
    <h3 style="margin-bottom:var(--space-lg)">Users by Role</h3>
    <canvas id="rolesChart" style="max-height:260px"></canvas>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-xl)">Top Scholarships</h3>
  <table class="modern-table">
    <thead><tr><th>Title</th><th>Applications</th></tr></thead>
    <tbody>
    <?php foreach ($stats['top_scholarships'] as $s): ?>
      <tr><td><?= htmlspecialchars($s['title']) ?></td><td><?= (int)$s['count'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Export Reports</h3>
  <form method="get" style="display:flex;gap:var(--space-md);align-items:end;flex-wrap:wrap">
    <input type="hidden" name="export" value="applications_csv">
    <div class="form-group" style="margin:0">
      <label class="form-label">Filter by Status</label>
      <select name="status" class="form-select">
        <option value="">All</option>
        <?php foreach ($stats['applications_by_status'] as $st): ?>
          <option value="<?= htmlspecialchars($st['status']) ?>"><?= htmlspecialchars($st['status']) ?> (<?= (int)$st['count'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">📥 Export Applications CSV</button>
  </form>
  <p class="text-muted" style="margin-top:var(--space-md)">Use the export button to download filtered application reports.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const statusLabels = <?= json_encode(array_column($stats['applications_by_status'],'status')) ?>;
    const statusCounts = <?= json_encode(array_column($stats['applications_by_status'],'count')) ?>;
    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, { type: 'pie', data: { labels: statusLabels, datasets: [{ data: statusCounts, backgroundColor: ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b'] }] } });

    const approvalCtx = document.getElementById('approvalChart').getContext('2d');
    new Chart(approvalCtx, { type: 'bar', data: { labels: ['Approved','Rejected'], datasets: [{ label: 'Count', data: [<?= (int)$stats['approved_count'] ?>, <?= (int)$stats['rejected_count'] ?>], backgroundColor:['#1cc88a','#e74a3b'] }] }, options: { scales: { y: { beginAtZero:true } } } });

    const roles = <?= json_encode(array_column($stats['users_by_role'],'role')) ?>;
    const roleCounts = <?= json_encode(array_column($stats['users_by_role'],'count')) ?>;
    const rolesCtx = document.getElementById('rolesChart').getContext('2d');
    new Chart(rolesCtx, { type: 'doughnut', data: { labels: roles, datasets: [{ data: roleCounts, backgroundColor:['#4e73df','#36b9cc','#f6c23e'] }] } });
  </script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
