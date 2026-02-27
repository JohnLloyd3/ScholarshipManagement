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

// Handle deletion by owner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_application') {
  $delId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($delId > 0) {
    try {
      $stmt = $pdo->prepare('SELECT id, user_id FROM applications WHERE id = :id LIMIT 1');
      $stmt->execute([':id' => $delId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $_SESSION['flash'] = 'Application not found.';
      } elseif ((int)$row['user_id'] !== (int)$user_id) {
        $_SESSION['flash'] = 'You are not authorized to delete this application.';
      } else {
        $pdo->prepare('DELETE FROM applications WHERE id = :id')->execute([':id' => $delId]);
        $_SESSION['success'] = 'Application deleted successfully.';
      }
    } catch (Exception $e) {
      $_SESSION['flash'] = 'Failed to delete application.';
    }
  } else {
    $_SESSION['flash'] = 'Invalid application ID.';
  }
  header('Location: applications.php');
  exit;
}

// Check if viewing details
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewingApp = null;
if ($viewId) {
    $stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.description as scholarship_desc, s.organization 
                           FROM applications a 
                           LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                           WHERE a.id = :aid AND a.user_id = :uid');
    $stmt->execute([':aid' => $viewId, ':uid' => $user_id]);
    $viewingApp = $stmt->fetch();
}
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
        <a href="notifications.php">Notifications</a>
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
        
        <?php if ($viewingApp): ?>
          <a href=\"applications.php\" style=\"color:#2196F3;text-decoration:none;margin-bottom:15px;display:inline-block\">← Back to Applications</a>
          <div style=\"margin-top:20px;background:#f9f9f9;padding:20px;border-radius:8px\">
            <h2><?= htmlspecialchars($viewingApp['scholarship_title']) ?></h2>
            <p style=\"color:#666;margin-bottom:20px\"><?= htmlspecialchars($viewingApp['scholarship_desc'] ?? '') ?></p>
            
            <div style=\"margin-bottom:20px\">
              <strong>Status:</strong> 
              <?php
                $status = $viewingApp['status'];
                $s = strtolower($status);
                $status_color = ['draft'=>'#999','submitted'=>'#2196F3','pending'=>'#FF9800','under_review'=>'#2196F3','approved'=>'#4CAF50','rejected'=>'#f44336','waitlisted'=>'#FFC107'];
                $color = $status_color[$s] ?? '#999';
              ?>
              <span style=\"color:<?= $color ?>;font-weight:bold\"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($status))) ?></span>
            </div>
            
            <div style=\"margin-bottom:20px\">
              <strong>Submitted:</strong> <?= htmlspecialchars($viewingApp['created_at']) ?>
            </div>
            
            <hr style=\"margin:20px 0\">
            <h4>Application Details</h4>
            <?php if ($viewingApp['motivational_letter']): ?>
              <?php $formData = json_decode($viewingApp['motivational_letter'], true); ?>
              <?php if ($formData): ?>
                <table style=\"width:100%;border-collapse:collapse\">
                  <?php foreach ($formData as $key => $value): ?>
                    <?php if (is_string($value) || is_numeric($value)): ?>
                      <tr style=\"border-bottom:1px solid #eee\">
                        <td style=\"padding:10px;font-weight:bold;width:30%\"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?></td>
                        <td style=\"padding:10px\"><?= htmlspecialchars($value) ?></td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </table>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php else: ?>
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
                <th>Actions</th>
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
                  <td>
                    <?php
                      $appTitle = 'Application';
                      if ($a['motivational_letter']) {
                        $formData = json_decode($a['motivational_letter'], true);
                        if ($formData && isset($formData['full_name'])) {
                          $appTitle = htmlspecialchars($formData['full_name']);
                        }
                      }
                      echo $appTitle;
                    ?>
                  </td>
                  <td>
                    <?php
                      $status = $a['status'];
                      $s = strtolower($status);
                      $status_color = [
                        'draft' => '#999',
                        'submitted' => '#2196F3',
                        'pending' => '#FF9800',
                        'under_review' => '#2196F3',
                        'approved' => '#4CAF50',
                        'rejected' => '#f44336',
                        'waitlisted' => '#FFC107'
                      ];
                      $color = $status_color[$s] ?? '#999';
                    ?>
                    <span style="color:<?= $color ?>">
                      <?= ucfirst(str_replace('_', ' ', htmlspecialchars($status))) ?>
                    </span>
                  </td>
                  <td><?php if (!empty($a['document'])): ?><a href="../<?= htmlspecialchars($a['document']) ?>" target="_blank">View</a><?php else: ?>—<?php endif; ?></td>
                  <td><small><?= htmlspecialchars($a['created_at']) ?></small></td>
                  <td>
                    <a href="applications.php?view=<?= $a['id'] ?>" style="color:#2196F3;text-decoration:none;margin-right:8px">View</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this application? This action cannot be undone.');">
                      <input type="hidden" name="action" value="delete_application">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button type="submit" style="background:transparent;border:none;color:#b91c1c;cursor:pointer;padding:0">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <?php endif; ?>
      </section>

    </main>
  </div>
</body>
</html>