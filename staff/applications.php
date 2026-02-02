<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role(['staff', 'admin']);

$pdo = getPDO();

$stmt = $pdo->query('
  SELECT a.*, u.username, u.first_name, u.last_name, s.title AS scholarship_title
  FROM applications a
  LEFT JOIN users u ON a.user_id = u.id
  LEFT JOIN scholarships s ON a.scholarship_id = s.id
  ORDER BY a.created_at DESC
');
$apps = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Applications | Staff</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar">S</div>
        <div>
          <div class="welcome">Staff</div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['username'] ?? '') ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="scholarships.php">Manage Scholarships</a>
        <a href="applications.php">View Applications</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>Applications</h2>
          <p class="muted">View all submitted applications</p>
        </div>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <h3>All Applications</h3>
        <?php if (!$apps): ?>
          <p class="muted">No applications yet.</p>
        <?php else: ?>
          <table style="width:100%;border-collapse:collapse;margin-top:12px">
            <thead>
              <tr>
                <th>#</th>
                <th>Scholarship</th>
                <th>Applicant</th>
                <th>Status</th>
                <th>Document</th>
                <th>Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($apps as $a): ?>
                <tr style="border-top:1px solid #eee">
                  <td><?= htmlspecialchars($a['id']) ?></td>
                  <td><?= htmlspecialchars($a['scholarship_title'] ?? '—') ?></td>
                  <td><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '') ?: ($a['username'] ?? '')) ?></td>
                  <td><?= htmlspecialchars($a['status']) ?></td>
                  <td><?php if (!empty($a['document'])): ?><a href="../<?= htmlspecialchars($a['document']) ?>" target="_blank">View</a><?php else: ?>—<?php endif; ?></td>
                  <td><small><?= htmlspecialchars($a['created_at']) ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>

