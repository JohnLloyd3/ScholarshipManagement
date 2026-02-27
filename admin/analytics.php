<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

require_role('admin');

$pdo = getPDO();
$stats = getDashboardStats($pdo);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Analytics & Reports | Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
  <style>
    * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif; }
    body { background: #f8f9fa; color: #2c3e50; }
    
    h2 { font-size: 28px; font-weight: 600; color: #1a1a1a; margin-bottom: 8px; letter-spacing: -0.5px; }
    h3 { font-size: 18px; font-weight: 600; color: #2c3e50; margin: 24px 0 16px; letter-spacing: -0.3px; }
    p { line-height: 1.6; font-size: 14px; color: #7f8c8d; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: white; padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-left: 4px solid #c41e3a; transition: all 0.3s ease; }
    .stat-card:hover { box-shadow: 0 8px 20px rgba(196,30,58,0.15); transform: translateY(-4px); }
    .stat-value { font-size: 42px; font-weight: 700; color: #c41e3a; line-height: 1; letter-spacing: -1px; }
    .stat-label { color: #7f8c8d; margin-top: 12px; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .chart-container { background: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    
    .panel { background: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { padding: 14px; text-align: left; border-bottom: 1px solid #ecf0f1; }
    th { background: #f8f9fa; font-weight: 600; color: #2c3e50; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
    td { color: #34495e; font-weight: 400; }
    tbody tr:hover { background: #f8f9fa; }
  </style>
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar">A</div>
        <div>
          <div class="welcome">Admin</div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Applications</a>
        <a href="scholarships.php">Scholarships</a>
        <a href="users.php">Users</a>
        <a href="analytics.php">Analytics</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>Analytics & Reports</h2>
          <p class="muted">System statistics and performance metrics</p>
        </div>
      </div>

      <!-- Statistics Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?= htmlspecialchars($stats['total_applications']) ?></div>
          <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= htmlspecialchars($stats['approved_count']) ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= htmlspecialchars($stats['rejected_count']) ?></div>
          <div class="stat-label">Rejected</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= htmlspecialchars($stats['total_scholarships']) ?></div>
          <div class="stat-label">Total Scholarships</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= htmlspecialchars($stats['open_scholarships']) ?></div>
          <div class="stat-label">Open Scholarships</div>
        </div>
      </div>

      <!-- Applications by Status -->
      <section class="panel">
        <h3>Applications by Status</h3>
        <table>
          <thead>
            <tr>
              <th>Status</th>
              <th>Count</th>
              <th>Percentage</th>
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
      </section>

      <!-- Top Scholarships -->
      <section class="panel">
        <h3>Most Applied Scholarships</h3>
        <table>
          <thead>
            <tr>
              <th>Scholarship Title</th>
              <th>Applications</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($stats['top_scholarships'] as $sch): ?>
              <?php if (!empty($sch['title'])): ?>
                <tr>
                  <td><?= htmlspecialchars($sch['title']) ?></td>
                  <td><?= htmlspecialchars($sch['count']) ?></td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <!-- Users by Role -->
      <section class="panel">
        <h3>Users by Role</h3>
        <table>
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
      </section>

    </main>
  </div>
</body>
</html>
