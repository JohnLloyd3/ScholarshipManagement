<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();

try {
    // Stats
    $totalApplications = $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn() ?: 0;
    $pendingApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn() ?: 0;
    $pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn() ?: 0;
    $totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0;
    $totalScholarships = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn() ?: 0;

    // Recent applications
    $stmt = $pdo->query('SELECT a.id, a.title, a.status, a.created_at, a.submitted_at, u.first_name, u.last_name, s.title as scholarship_title 
                         FROM applications a 
                         LEFT JOIN users u ON a.user_id = u.id 
                         LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                         ORDER BY a.created_at DESC LIMIT 12');
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    $totalApplications = 0;
    $pendingApplications = 0;
    $pendingReviews = 0;
    $totalUsers = 0;
    $totalScholarships = 0;
    $recent = [];
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
  <style>
    * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif; }
    body { background: #f8f9fa; color: #1a1a1a; }
    h2 { font-size: 28px; font-weight: 600; color: #1a1a1a; letter-spacing: -0.5px; }
    h3 { font-size: 18px; font-weight: 600; color: #1a1a1a; letter-spacing: -0.5px; }
    p { line-height: 1.6; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .stat-card { background: white; padding: 24px; border-radius: 12px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-left: 4px solid #c41e3a; transition: all 0.3s ease; }
    .stat-card:hover { box-shadow: 0 8px 20px rgba(196,30,58,0.15); transform: translateY(-4px); }
    .stat-card .value { font-size: 42px; font-weight: 700; color: #c41e3a; line-height: 1; letter-spacing: -1px; }
    .stat-card .label { color: #7f8c8d; margin-top: 12px; font-size: 13px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .panel { background: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-top: 3px solid #c41e3a; }
    .panel h3 { margin-top: 0; margin-bottom: 16px; }
    
    .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 500; background-color: #c41e3a; color: white; transition: all 0.3s ease; }
    .btn:hover { background-color: #9d1729; transform: translateY(-2px); }
    
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    table th, table td { padding: 14px; text-align: left; border-bottom: 1px solid #ecf0f1; }
    table th { background-color: #f8f9fa; font-weight: 600; color: #1a1a1a; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
    table td { color: #34495e; }
    table tbody tr:hover { background: #f8f9fa; }
    
    button { padding: 8px 16px; border: 1px solid #c41e3a; background: white; color: #c41e3a; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s ease; margin-right: 8px; }
    button:hover { background: #c41e3a; color: white; }
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
          <h2>Admin Dashboard</h2>
          <p class="muted">Overview of the system</p>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="value"><?= htmlspecialchars($totalApplications) ?></div><div class="label">Total Applications</div></div>
        <div class="stat-card"><div class="value"><?= htmlspecialchars($pendingReviews) ?></div><div class="label">Pending Reviews</div></div>
        <div class="stat-card"><div class="value"><?= htmlspecialchars($totalUsers) ?></div><div class="label">Total Users</div></div>
        <div class="stat-card"><div class="value">—</div><div class="label">System</div></div>
      </div>

      <section class="panel">
        <h3>Master Controls</h3>
        <p class="muted">Quick access to manage every part of the system.</p>
        <p>
          <a class="btn" href="users.php">Manage Users</a>
          <a class="btn" href="scholarships.php" style="margin-left:10px">Manage Scholarships</a>
          <a class="btn" href="applications.php" style="margin-left:10px">Manage Applications</a>
        </p>
      </section>

      <section class="panel">
        <h3>Recent Applications</h3>
        <table style="width:100%;border-collapse:collapse">
          <thead><tr><th>#</th><th>Applicant</th><th>Title</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr style="border-top:1px solid #eee">
                <td><?= htmlspecialchars($r['id']) ?></td>
                <td><?= htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
                <td>
                  <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit">Assign</button>
                  </form>
                  <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

    </main>
  </div>
</body>
</html>