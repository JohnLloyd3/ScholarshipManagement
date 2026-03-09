<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

require_role('admin');

$pdo = getPDO();
$stats = getDashboardStats($pdo);

// Export handlers (CSV/Excel/PDF)
if (!empty($_GET['export'])) {
  $what = $_GET['export'];
  $format = strtolower($_GET['format'] ?? 'csv');

  // Ensure only admins can trigger exports (redundant with require_role but explicit here)
  if (!is_role('admin')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }

  // Determine dataset
  if ($what === 'applications') {
    $data = getApplicationsReport($pdo, []);
    $label = 'applications';
  } elseif ($what === 'top_scholarships') {
    $data = $stats['top_scholarships'];
    $label = 'top_scholarships';
  } elseif ($what === 'users_by_role') {
    $data = $stats['users_by_role'];
    $label = 'users_by_role';
  } else {
    $data = [];
    $label = 'export';
  }

  // Log the export action
  if (function_exists('logAuditTrail')) {
    logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'EXPORT', $label, 0, 'format: ' . $format);
  }

  // Choose export format
  if ($format === 'pdf') {
    exportDataToPDF($data, $label . '.pdf', ucfirst(str_replace('_', ' ', $label)));
  } elseif ($format === 'excel' || $format === 'xlsx') {
    // Prefer real XLSX if PhpSpreadsheet available
    $outName = $label . '.xlsx';
    exportToXLSX($data, $outName);
  } else {
    // default CSV
    exportToCSV($data, $label . '.csv');
  }
}
?>
<?php
$page_title = 'Analytics & Reports - Admin';
$base_path = '../';
$extra_css = '
  .compact-stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--space-md); margin-bottom: var(--space-lg); }
  .compact-stat-card { background: var(--white); padding: var(--space-md); border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-sm); border-left: 3px solid var(--red-primary); }
  .compact-stat-value { font-size: 1.75rem; font-weight: 700; color: var(--red-primary); line-height: 1; }
  .compact-stat-label { color: var(--gray-600); margin-top: var(--space-xs); font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
  .analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg); margin-bottom: var(--space-lg); }
  .analytics-card { background: var(--white); padding: var(--space-lg); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
  .analytics-card h3 { font-size: 1rem; margin-bottom: var(--space-md); color: var(--gray-800); }
  .analytics-card canvas { max-height: 200px; }
  .compact-table { font-size: 0.875rem; }
  .compact-table th, .compact-table td { padding: var(--space-sm) var(--space-md); }
  .export-buttons { display: flex; flex-wrap: wrap; gap: var(--space-xs); margin-bottom: var(--space-md); }
  .export-buttons .btn { padding: var(--space-xs) var(--space-sm); font-size: 0.75rem; }
  @media (max-width: 1400px) {
    .compact-stats-grid { grid-template-columns: repeat(3, 1fr); }
    .analytics-grid { grid-template-columns: 1fr; }
  }
';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="padding: var(--space-lg); margin-bottom: var(--space-lg);">
  <h1 style="font-size: 1.5rem; margin-bottom: var(--space-xs);">📊 Analytics & Reports</h1>
  <p class="text-muted" style="font-size: 0.875rem;">System statistics and performance metrics</p>
</div>

<div class="compact-stats-grid">
  <div class="compact-stat-card">
    <div class="compact-stat-value"><?= htmlspecialchars($stats['total_applications']) ?></div>
    <div class="compact-stat-label">Applications</div>
  </div>
  <div class="compact-stat-card">
    <div class="compact-stat-value"><?= htmlspecialchars($stats['approved_count']) ?></div>
    <div class="compact-stat-label">Approved</div>
  </div>
  <div class="compact-stat-card">
    <div class="compact-stat-value"><?= htmlspecialchars($stats['rejected_count']) ?></div>
    <div class="compact-stat-label">Rejected</div>
  </div>
  <div class="compact-stat-card">
    <div class="compact-stat-value"><?= htmlspecialchars($stats['total_scholarships']) ?></div>
    <div class="compact-stat-label">Scholarships</div>
  </div>
  <div class="compact-stat-card">
    <div class="compact-stat-value"><?= htmlspecialchars($stats['open_scholarships']) ?></div>
    <div class="compact-stat-label">Open</div>
  </div>
</div>

<div class="analytics-grid">
  <div class="analytics-card">
    <h3>Applications by Status</h3>
    <canvas id="statusChart" height="200"></canvas>
  </div>
  
  <div class="analytics-card">
    <h3>Top Scholarships</h3>
    <canvas id="scholarshipChart" height="200"></canvas>
  </div>
</div>

<div class="content-card" style="padding: var(--space-lg);">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-md);">
    <h3 style="font-size: 1rem; margin: 0;">Export Data</h3>
  </div>
  <div class="export-buttons">
    <a class="btn btn-primary" href="?export=applications&format=csv">📊 Applications CSV</a>
    <a class="btn btn-primary" href="?export=applications&format=excel">📊 Applications Excel</a>
    <a class="btn btn-primary" href="?export=applications&format=pdf">📊 Applications PDF</a>
    <a class="btn btn-primary" href="?export=top_scholarships&format=csv">🎓 Scholarships CSV</a>
    <a class="btn btn-primary" href="?export=top_scholarships&format=excel">🎓 Scholarships Excel</a>
    <a class="btn btn-primary" href="?export=top_scholarships&format=pdf">🎓 Scholarships PDF</a>
    <a class="btn btn-primary" href="?export=users_by_role&format=csv">👥 Users CSV</a>
    <a class="btn btn-primary" href="?export=users_by_role&format=excel">👥 Users Excel</a>
    <a class="btn btn-primary" href="?export=users_by_role&format=pdf">👥 Users PDF</a>
  </div>
  
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg);">
    <div>
      <h4 style="font-size: 0.875rem; margin-bottom: var(--space-sm); color: var(--gray-700);">Status Breakdown</h4>
      <table class="modern-table compact-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Count</th>
            <th>%</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $total = $stats['total_applications'] ?: 1;
          foreach ($stats['applications_by_status'] as $item):
              $percentage = ($item['count'] / $total) * 100;
          ?>
            <tr>
              <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['status']))) ?></td>
              <td><?= htmlspecialchars($item['count']) ?></td>
              <td><?= round($percentage, 1) ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <div>
      <h4 style="font-size: 0.875rem; margin-bottom: var(--space-sm); color: var(--gray-700);">Users by Role</h4>
      <table class="modern-table compact-table">
        <thead>
          <tr>
            <th>Role</th>
            <th>Count</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($stats['users_by_role'] as $role): ?>
            <tr>
              <td><?= htmlspecialchars(ucfirst($role['role'])) ?></td>
              <td><?= htmlspecialchars($role['count']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const statusData = <?= json_encode(array_column($stats['applications_by_status'], 'count')) ?>;
  const statusLabels = <?= json_encode(array_map(function($i){ return ucfirst(str_replace('_',' ',$i['status'])); }, $stats['applications_by_status'])) ?>;
  const topLabels = <?= json_encode(array_map(function($s){ return $s['title']; }, $stats['top_scholarships'])) ?>;
  const topData = <?= json_encode(array_map(function($s){ return (int)$s['count']; }, $stats['top_scholarships'])) ?>;

  // Status pie chart
  try {
    const ctx = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: statusLabels,
        datasets: [{ 
          data: statusData, 
          backgroundColor: ['#c41e3a','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899']
        }]
      },
      options: { 
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } }
        }
      }
    });
  } catch (e) {}

  // Top scholarships bar chart
  try {
    const ctx2 = document.getElementById('scholarshipChart').getContext('2d');
    new Chart(ctx2, {
      type: 'bar',
      data: { 
        labels: topLabels, 
        datasets: [{ 
          label: 'Applications', 
          data: topData, 
          backgroundColor: '#c41e3a' 
        }] 
      },
      options: { 
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: { 
          y: { beginAtZero: true, ticks: { font: { size: 10 } } },
          x: { ticks: { font: { size: 10 } } }
        }
      }
    });
  } catch (e) {}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
