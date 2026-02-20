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
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .stat-value { font-size: 32px; font-weight: bold; color: #c41e3a; }
    .stat-label { color: #666; margin-top: 5px; }
    .chart-container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #f5f5f5; font-weight: bold; }
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
