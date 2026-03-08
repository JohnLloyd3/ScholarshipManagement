<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');
$pdo = getPDO();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
$limit = min(max($limit, 10), 1000);

$stmt = $pdo->prepare('SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT :lim');
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Activity Logs | Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile"><div class="avatar">A</div><div><div class="welcome">Admin</div><div class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></div></div></div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="analytics.php">Analytics</a>
        <a href="activity_logs.php">Activity Logs</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>
    <main class="main">
      <div class="header-row"><h2>System Activity Log</h2><p class="muted">Recent system actions and audit trail</p></div>
      <section class="panel">
        <form method="get" style="margin-bottom:12px">Limit: <input type="number" name="limit" value="<?= htmlspecialchars($limit) ?>" style="width:100px"> <button class="btn">Apply</button></form>
        <table>
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Entity ID</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= htmlspecialchars($l['created_at']) ?></td>
                <td><?= htmlspecialchars($l['username'] ?? 'System') ?></td>
                <td><?= htmlspecialchars($l['action']) ?></td>
                <td><?= htmlspecialchars($l['entity_type'] ?? $l['target_table'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['entity_id'] ?? $l['target_id'] ?? '') ?></td>
                <td><pre style="white-space:pre-wrap;max-width:600px;overflow:auto"><?= htmlspecialchars($l['new_values'] ?? $l['description'] ?? '') ?></pre></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
