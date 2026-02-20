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

// Filters
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$reviewer = trim($_GET['reviewer_id'] ?? '');
$scholarship = trim($_GET['scholarship_id'] ?? '');
$editId = (int)($_GET['edit'] ?? 0);

// Load dropdown data
$reviewers = $pdo->query("SELECT id, username FROM users WHERE role = 'reviewer' ORDER BY username")->fetchAll();
$scholarships = $pdo->query("SELECT id, title FROM scholarships ORDER BY created_at DESC")->fetchAll();

$sql = '
  SELECT a.*, u.username, s.title AS scholarship_title
  FROM applications a
  LEFT JOIN users u ON a.user_id = u.id
  LEFT JOIN scholarships s ON a.scholarship_id = s.id
  WHERE 1=1
';
$params = [];

if ($q !== '') {
  $sql .= ' AND (a.title LIKE :q OR u.username LIKE :q OR a.email LIKE :q OR s.title LIKE :q)';
  $params[':q'] = '%' . $q . '%';
}
if ($status !== '' && in_array($status, ['submitted','pending','approved','rejected'], true)) {
  $sql .= ' AND a.status = :st';
  $params[':st'] = $status;
}
if ($reviewer !== '' && ctype_digit($reviewer)) {
  $sql .= ' AND a.reviewer_id = :rid';
  $params[':rid'] = (int)$reviewer;
}
if ($scholarship !== '' && ctype_digit($scholarship)) {
  $sql .= ' AND a.scholarship_id = :sid';
  $params[':sid'] = (int)$scholarship;
}

$sql .= ' ORDER BY a.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll();

// Load edit application
$editApp = null;
if ($editId) {
  $st = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
  $st->execute([':id' => $editId]);
  $editApp = $st->fetch();
}
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
        <a href="scholarships.php">Scholarships</a>
        <a href="users.php">Users</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h2>Manage Applications</h2>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel" style="margin-top:12px;">
        <h3>Search & Filters</h3>
        <form method="GET" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
          <div class="form-group" style="margin:0; min-width:240px;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="app title, applicant, email, scholarship">
          </div>
          <div class="form-group" style="margin:0;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Status</label>
            <select name="status">
              <option value="">All</option>
              <option value="submitted" <?= $status==='submitted'?'selected':'' ?>>Submitted</option>
              <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
              <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
              <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Reviewer</label>
            <select name="reviewer_id">
              <option value="">All</option>
              <?php foreach ($reviewers as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= ((string)$reviewer === (string)$r['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r['username']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Scholarship</label>
            <select name="scholarship_id">
              <option value="">All</option>
              <?php foreach ($scholarships as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= ((string)$scholarship === (string)$s['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <button type="submit" class="submit-btn" style="padding:10px 14px;">Filter</button>
            <a href="applications.php" style="margin-left:10px;">Reset</a>
          </div>
        </form>
      </section>

      <?php if ($editApp): ?>
        <section class="panel" style="margin-top:12px;">
          <h3>Edit Application #<?= (int)$editApp['id'] ?></h3>
          <form method="POST" action="../controllers/AdminController.php">
            <input type="hidden" name="action" value="update_application">
            <input type="hidden" name="id" value="<?= (int)$editApp['id'] ?>">

            <div class="form-group">
              <label style="display:block;font-weight:bold;margin-bottom:6px;">Title</label>
              <input type="text" name="title" value="<?= htmlspecialchars($editApp['title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label style="display:block;font-weight:bold;margin-bottom:6px;">Details</label>
              <textarea name="details" rows="6"><?= htmlspecialchars($editApp['details'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label style="display:block;font-weight:bold;margin-bottom:6px;">Status</label>
              <select name="status">
                <?php foreach (['submitted','pending','approved','rejected'] as $st): ?>
                  <option value="<?= $st ?>" <?= ($editApp['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label style="display:block;font-weight:bold;margin-bottom:6px;">Reviewer comments (included in notification email)</label>
              <textarea name="review_comments" rows="3" placeholder="Optional message to applicant when approving/rejecting"><?= htmlspecialchars($_POST['review_comments'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label style="display:block;font-weight:bold;margin-bottom:6px;">Reviewer</label>
              <select name="reviewer_id">
                <option value="">— None —</option>
                <?php foreach ($reviewers as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= ((string)($editApp['reviewer_id'] ?? '') === (string)$r['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['username']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label style="display:block;font-weight:bold;margin-bottom:6px;">Document</label>
              <?php if (!empty($editApp['document'])): ?>
                <a href="../<?= htmlspecialchars($editApp['document']) ?>" target="_blank">View Document</a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </div>

            <button type="submit" class="submit-btn">Save Changes</button>
            <a href="applications.php" style="margin-left:10px;">Cancel</a>
          </form>
        </section>
      <?php endif; ?>

      <table style="width:100%;border-collapse:collapse;margin-top:12px">
        <thead><tr><th>#</th><th>Scholarship</th><th>Applicant</th><th>Title</th><th>Status</th><th>Reviewer</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($apps as $a): ?>
            <tr style="border-top:1px solid #eee">
              <td><?= htmlspecialchars($a['id']) ?></td>
              <td><?= htmlspecialchars($a['scholarship_title'] ?? '—') ?></td>
              <td><?= htmlspecialchars($a['username'] ?? ($a['email'] ?? '')) ?></td>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['status']) ?></td>
              <td><?= htmlspecialchars($a['reviewer_id'] ?? '-') ?></td>
              <td>
                <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                  <input type="hidden" name="action" value="assign">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <select name="reviewer_id">
                    <?php
                    foreach ($reviewers as $r) {
                      $sel = ((string)($a['reviewer_id'] ?? '') === (string)$r['id']) ? ' selected' : '';
                      echo '<option value="' . (int)$r['id'] . '"' . $sel . '>' . htmlspecialchars($r['username']) . '</option>';
                    }
                    ?>
                  </select>
                  <button type="submit">Assign</button>
                </form>

                <a href="?edit=<?= (int)$a['id'] ?>" style="margin-left:10px;">Edit</a>

                <form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Force approve this application?');">
                  <input type="hidden" name="action" value="set_application_status">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="status" value="approved">
                  <button type="submit" style="margin-left:10px;">Approve</button>
                </form>
                <form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Force reject this application?');">
                  <input type="hidden" name="action" value="set_application_status">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="status" value="rejected">
                  <button type="submit">Reject</button>
                </form>
                <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                  <input type="hidden" name="action" value="set_application_status">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="status" value="pending">
                  <button type="submit">Pending</button>
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