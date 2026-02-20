<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role(['staff', 'admin']);

$pdo = getPDO();

$totalOpenScholarships = $pdo->query('SELECT COUNT(*) FROM scholarships WHERE status = "open"')->fetchColumn();
$totalApplications = $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$pendingApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','pending')")->fetchColumn();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Staff Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../assets/staff-dashboard.css">
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
        <a href="applications.php">View Applications</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>Staff Dashboard</h2>
          <p class="muted">Manage scholarships and monitor applications</p>
        </div>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="value" style="font-size:2.2rem;color:#b71c1c;font-weight:700;"><?= htmlspecialchars($totalOpenScholarships) ?></div>
          <div class="label" style="color:#444;font-size:1.1rem;margin-bottom:8px;">Open Scholarships</div>
        </div>
        <div class="stat-card">
          <div class="value" style="font-size:2.2rem;color:#b71c1c;font-weight:700;"><?= htmlspecialchars($totalApplications) ?></div>
          <div class="label" style="color:#444;font-size:1.1rem;margin-bottom:8px;">Total Applications</div>
        </div>
        <div class="stat-card">
          <div class="value" style="font-size:2.2rem;color:#b71c1c;font-weight:700;"><?= htmlspecialchars($pendingApplications) ?></div>
          <div class="label" style="color:#444;font-size:1.1rem;margin-bottom:8px;">Pending/Submitted</div>
        </div>
      </div>

      <section class="panel">
        <h3>Quick Actions</h3>
        <div class="quick-actions">
            <button class="btn" onclick="location.href='../auth/applicant_register.php'">Applicant Registration Form</button>
            <button class="btn" onclick="location.href='post_scholarship.php'">Scholarship Posting Form</button>
            <button class="btn" onclick="location.href='scholarships.php'">Create/Edit Scholarships</button>
            <button class="btn" onclick="location.href='applications.php'">View Applications</button>
        </div>
      </section>
    </main>
  </div>
</body>
</html>

