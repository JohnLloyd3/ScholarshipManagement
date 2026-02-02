<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'reviewer') {
    $_SESSION['flash'] = 'Reviewer access only.';
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT r.*, a.title, u.username FROM reviews r JOIN applications a ON r.application_id = a.id LEFT JOIN users u ON a.user_id = u.id WHERE r.reviewer_id = :rid ORDER BY r.created_at DESC');
$stmt->execute([':rid' => $user_id]);
$reviews = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reviewer Dashboard</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar">R</div>
        <div>
          <div class="welcome">Reviewer</div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="#">Assigned Reviews</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h2>Reviewer Dashboard</h2>

      <section class="panel">
        <h3>Your Assigned Reviews</h3>
        <?php if (!$reviews): ?>
          <p class="muted">No reviews assigned yet.</p>
        <?php else: ?>
          <table style="width:100%;border-collapse:collapse">
            <thead><tr><th>#</th><th>Application</th><th>Applicant</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($reviews as $rv): ?>
                <tr style="border-top:1px solid #eee">
                  <td><?= htmlspecialchars($rv['id']) ?></td>
                  <td><?= htmlspecialchars($rv['title']) ?></td>
                  <td><?= htmlspecialchars($rv['username'] ?? '') ?></td>
                  <td><?= htmlspecialchars($rv['status']) ?></td>
                  <td>
                    <form method="POST" action="../controllers/ReviewerController.php" style="display:inline">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="id" value="<?= $rv['id'] ?>">
                      <button type="submit">Approve</button>
                    </form>
                    <form method="POST" action="../controllers/ReviewerController.php" style="display:inline">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="id" value="<?= $rv['id'] ?>">
                      <button type="submit">Reject</button>
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