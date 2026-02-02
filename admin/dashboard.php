<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

// Only admin can access
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    $_SESSION['flash'] = 'Admin access only.';
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getPDO();

// Stats
$totalApplications = $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
$totalUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

// Recent applications
$stmt = $pdo->query('SELECT a.*, u.first_name, u.last_name FROM applications a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 12');
$recent = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
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