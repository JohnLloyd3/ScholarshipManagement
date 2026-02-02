<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    $_SESSION['flash'] = 'Admin access only.';
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getPDO();
$stmt = $pdo->query('SELECT a.*, u.username FROM applications a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC');
$apps = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Applications</title>
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
        <a href="applications.php">Manage Applications</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h2>Manage Applications</h2>
      <table style="width:100%;border-collapse:collapse;margin-top:12px">
        <thead><tr><th>#</th><th>Applicant</th><th>Title</th><th>Status</th><th>Reviewer</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($apps as $a): ?>
            <tr style="border-top:1px solid #eee">
              <td><?= htmlspecialchars($a['id']) ?></td>
              <td><?= htmlspecialchars($a['username'] ?? '') ?></td>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['status']) ?></td>
              <td><?= htmlspecialchars($a['reviewer_id'] ?? '-') ?></td>
              <td>
                <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                  <input type="hidden" name="action" value="assign">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <select name="reviewer_id">
                    <?php
                    $rs = $pdo->query("SELECT id, username FROM users WHERE role = 'reviewer'")->fetchAll();
                    foreach ($rs as $r) {
                      echo '<option value="' . $r['id'] . '">' . htmlspecialchars($r['username']) . '</option>';
                    }
                    ?>
                  </select>
                  <button type="submit">Assign</button>
                </form>
                <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>