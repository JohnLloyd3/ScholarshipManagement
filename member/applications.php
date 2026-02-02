<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please log in.';
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';
$pdo = getPDO();
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.organization, s.status as scholarship_status 
                       FROM applications a 
                       LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                       WHERE a.user_id = :uid 
                       ORDER BY a.created_at DESC');
$stmt->execute([':uid' => $user_id]);
$apps = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Your Applications</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar"><?= strtoupper(substr(($_SESSION['user']['first_name']??$_SESSION['user']['username']),0,1)) ?></div>
        <div>
          <div class="welcome">Welcome</div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['first_name'] ?? $_SESSION['user']['username']) ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Your Applications</a>
        <a href="apply_scholarship.php">Apply for Scholarship</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>
    <main class="main">
      <h2>Your Applications</h2>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <h3>My Applications</h3>
        <?php if (empty($apps)): ?>
          <p class="muted">You have not submitted any applications yet.</p>
          <p><a href="apply_scholarship.php" class="btn">Apply for a Scholarship</a></p>
        <?php else: ?>
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr>
                <th>#</th>
                <th>Scholarship</th>
                <th>Title</th>
                <th>Status</th>
                <th>Document</th>
                <th>Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($apps as $a): ?>
                <tr style="border-top:1px solid #eee">
                  <td><?= htmlspecialchars($a['id']) ?></td>
                  <td>
                    <?php if ($a['scholarship_title']): ?>
                      <strong><?= htmlspecialchars($a['scholarship_title']) ?></strong><br>
                      <small><?= htmlspecialchars($a['organization'] ?? 'N/A') ?></small>
                    <?php else: ?>
                      <em>General Application</em>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($a['title']) ?></td>
                  <td>
                    <span style="color:<?= $a['status'] == 'approved' ? 'green' : ($a['status'] == 'rejected' ? 'red' : 'orange') ?>">
                      <?= ucfirst(htmlspecialchars($a['status'])) ?>
                    </span>
                  </td>
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