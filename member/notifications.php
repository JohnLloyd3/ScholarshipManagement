<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_login();
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Mark as seen when viewing
if (isset($_GET['seen']) && ctype_digit($_GET['seen'])) {
    $sid = (int)$_GET['seen'];
    $pdo->prepare('UPDATE notifications SET seen = 1 WHERE id = :id AND user_id = :uid')->execute([':id' => $sid, ':uid' => $user_id]);
    header('Location: notifications.php');
    exit;
}

// Mark all as seen
if (isset($_POST['mark_all_seen'])) {
    $pdo->prepare('UPDATE notifications SET seen = 1 WHERE user_id = :uid')->execute([':uid' => $user_id]);
    $_SESSION['success'] = 'All notifications marked as read.';
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC');
$stmt->execute([':uid' => $user_id]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['seen']) $unreadCount++;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Notifications | Scholarship Management</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .notif-item { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 14px; margin-bottom: 10px; }
    .notif-item.unread { border-left: 4px solid #2196F3; background: #f8fbff; }
    .notif-item .type-success { color: #2e7d32; }
    .notif-item .type-warning { color: #c62828; }
    .notif-item .type-info { color: #1565c0; }
    .notif-item small { color: #666; }
  </style>
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar"><?= strtoupper(substr(($_SESSION['user']['first_name']??$_SESSION['user']['username']),0,1)) ?></div>
        <div>
          <div class="welcome">Welcome,</div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['first_name'] ?? $_SESSION['user']['username']) ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Your Applications</a>
        <a href="apply_scholarship.php">Apply for Scholarship</a>
        <a href="notifications.php">Notifications <?= $unreadCount > 0 ? '<span style="background:#e53935;color:#fff;padding:2px 6px;border-radius:10px;font-size:11px">' . $unreadCount . '</span>' : '' ?></a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <h2>Notifications</h2>
        <?php if (count($notifications) > 0): ?>
          <form method="POST" style="margin-left:auto;">
            <input type="hidden" name="mark_all_seen" value="1">
            <button type="submit" class="btn secondary">Mark all as read</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <?php if (empty($notifications)): ?>
          <p class="muted">You have no notifications.</p>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <div class="notif-item <?= $n['seen'] ? '' : 'unread' ?>">
              <span class="type-<?= htmlspecialchars($n['type']) ?>"><strong><?= htmlspecialchars($n['title']) ?></strong></span>
              <p style="margin:8px 0 4px"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
              <small><?= htmlspecialchars($n['created_at']) ?></small>
              <?php if (!$n['seen']): ?>
                <a href="?seen=<?= (int)$n['id'] ?>" style="margin-left:10px;font-size:12px">Mark as read</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
