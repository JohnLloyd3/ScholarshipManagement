<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');
$pdo = getPDO();

// Handle restore or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'restore' && $id > 0) {
        $pdo->prepare("UPDATE scholarships SET status = 'open' WHERE id = :id")->execute([':id' => $id]);
        if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'SCHOLARSHIP_RESTORED', 'scholarships', $id, 'Restored from archive');
        $_SESSION['success'] = 'Scholarship restored.';
    } elseif ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM scholarships WHERE id = :id')->execute([':id' => $id]);
        if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'SCHOLARSHIP_DELETED', 'scholarships', $id, 'Deleted from archive');
        $_SESSION['success'] = 'Scholarship deleted permanently.';
    }
    header('Location: scholarship_archive.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM scholarships WHERE status = 'archived' OR is_archived = 1 ORDER BY updated_at DESC");
$stmt->execute();
$archived = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Scholarship Archive | Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile"><div class="avatar">A</div><div><div class="welcome">Admin</div><div class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></div></div></div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="scholarships.php">Scholarships</a>
        <a href="scholarship_archive.php">Archive</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>
    <main class="main">
      <div class="header-row"><h2>Scholarship Archive</h2><p class="muted">Past scholarships</p></div>
      <section class="panel">
        <?php if (empty($archived)): ?>
          <p>No archived scholarships.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>Title</th><th>Organization</th><th>Deadline</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($archived as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['title']) ?></td>
                  <td><?= htmlspecialchars($a['organization'] ?? '') ?></td>
                  <td><?= htmlspecialchars($a['deadline'] ?? '') ?></td>
                  <td><?= htmlspecialchars($a['updated_at'] ?? $a['created_at'] ?? '') ?></td>
                  <td>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button name="action" value="restore" class="btn">Restore</button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete permanently?');">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button name="action" value="delete" class="btn btn-danger">Delete</button>
                    </form>
                  </td>
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
