<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role(['staff', 'admin']);

$pdo = getPDO();

// Handle deletion requests from staff/admin/reviewer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_application') {
  $delId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($delId > 0) {
    try {
      $stmt = $pdo->prepare('SELECT id FROM applications WHERE id = :id');
      $stmt->execute([':id' => $delId]);
      $exists = $stmt->fetch();
      if ($exists) {
        $pdo->prepare('DELETE FROM applications WHERE id = :id')->execute([':id' => $delId]);
        $_SESSION['success'] = 'Application deleted successfully.';
      } else {
        $_SESSION['flash'] = 'Application not found.';
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

// Handle status change (approve/reject) by staff/admin/reviewer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
  $chgId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $newStatus = trim($_POST['new_status'] ?? '');
  $comments = trim($_POST['reason'] ?? $_POST['comments'] ?? '');
  if ($chgId > 0 && in_array($newStatus, ['Approved','Rejected','Pending','Completed','Submitted'])) {
    try {
      $stmt = $pdo->prepare('SELECT id, user_id, scholarship_id, status FROM applications WHERE id = :id');
      $stmt->execute([':id' => $chgId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        // only allow approve/reject when application is under_review
        if (strtolower($row['status']) !== 'under_review' && in_array($newStatus, ['Approved','Rejected'])) {
          $_SESSION['flash'] = 'Can only approve or reject applications that are currently under review.';
        } else {
          $update = $pdo->prepare('UPDATE applications SET status = :status, updated_at = NOW() WHERE id = :id');
          $update->execute([':status' => $newStatus, ':id' => $chgId]);

        // Create a notification for the applicant
        $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id) VALUES (:user_id, :title, :message, :type, :app_id, :sch_id)');
        $title = ($newStatus === 'Approved') ? 'Application Approved' : (($newStatus === 'Rejected') ? 'Application Rejected' : 'Application Status Updated');
        $message = 'Your application (ID ' . $chgId . ') status has been updated to ' . $newStatus . '.';
        if ($comments) $message .= ' Comments: ' . $comments;
        $notif->execute([
          ':user_id' => $row['user_id'],
          ':title' => $title,
          ':message' => $message,
          ':type' => 'info',
          ':app_id' => $chgId,
          ':sch_id' => $row['scholarship_id'] ?? null
        ]);

        // Send/queue an email to applicant
        try {
          $userStmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id');
          $userStmt->execute([':id' => $row['user_id']]);
          $user = $userStmt->fetch(PDO::FETCH_ASSOC);
          if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            // get scholarship title if available
            $schTitle = '';
            if (!empty($row['scholarship_id'])) {
              $schS = $pdo->prepare('SELECT title FROM scholarships WHERE id = :id');
              $schS->execute([':id' => $row['scholarship_id']]);
              $sch = $schS->fetch(PDO::FETCH_ASSOC);
              $schTitle = $sch['title'] ?? '';
            }
            // prepare email body using existing template helper if available
            if (function_exists('getApplicationDecisionEmailTemplate')) {
              $emailBody = getApplicationDecisionEmailTemplate($user['first_name'] ?? '', strtolower($schTitle), strtolower($newStatus), $comments);
            } else {
              $emailBody = '<p>Your application status has been updated to ' . htmlspecialchars($newStatus) . '.</p>' . ($comments?'<p>Comments: '.htmlspecialchars($comments).'</p>':'');
            }
            if (function_exists('queueEmail')) {
              queueEmail($user['email'], $title . ' - ' . ($schTitle? $schTitle : ''), $emailBody, $row['user_id']);
            } else {
              // fallback to sendEmail if queue not present
              if (function_exists('sendEmail')) sendEmail($user['email'], $title . ' - ' . ($schTitle? $schTitle : ''), $emailBody, true);
            }
          }
        } catch (Exception $ee) {
          // ignore email failures
        }

        $_SESSION['success'] = 'Application status updated to ' . $newStatus . '.';
        }
      } else {
        $_SESSION['flash'] = 'Application not found.';
      }
    } catch (Exception $e) {
      $_SESSION['flash'] = 'Failed to update status.';
    }
  } else {
    $_SESSION['flash'] = 'Invalid status or application id.';
  }
  // redirect back to the view or list
  $redirect = 'applications.php';
  if (!empty($_POST['return_to_view']) && $chgId>0) $redirect = 'applications.php?view=' . $chgId;
  header('Location: ' . $redirect);
  exit;
}

// Check if viewing details
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$applicant_filter = $_GET['applicant'] ?? '';
$scholarship_filter = $_GET['scholarship'] ?? '';
$viewingApp = null;
if ($viewId) {
    $stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.description as scholarship_desc, s.organization, u.first_name, u.last_name, u.email
                           FROM applications a 
                           LEFT JOIN scholarships s ON a.scholarship_id = s.id
                           LEFT JOIN users u ON a.user_id = u.id
                           WHERE a.id = :aid');
    $stmt->execute([':aid' => $viewId]);
    $viewingApp = $stmt->fetch();
}

if (!$viewId) {
  $allowed_sort = ['created_at','status','scholarship_title','username'];
  $allowed_dir = ['asc','desc'];
  $params = [];
  $sql = 'SELECT a.*, u.username, u.first_name, u.last_name, s.title AS scholarship_title FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN scholarships s ON a.scholarship_id = s.id';
  $where = [];
  if ($status_filter && in_array($status_filter, ['Pending', 'Submitted', 'Approved', 'Completed', 'Rejected'])) {
    $where[] = 'a.status = :status';
    $params['status'] = $status_filter;
  }
  if ($applicant_filter) {
    $where[] = '(u.first_name LIKE :applicant OR u.last_name LIKE :applicant OR u.username LIKE :applicant)';
    $params['applicant'] = "%$applicant_filter%";
  }
  if ($scholarship_filter) {
    $where[] = 's.title LIKE :scholarship';
    $params['scholarship'] = "%$scholarship_filter%";
  }
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY ' . (in_array($sort_by,$allowed_sort)?$sort_by:'created_at') . ' ' . (in_array($sort_dir,$allowed_dir)?$sort_dir:'desc');
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $apps = $stmt->fetchAll();

  // Statistics for summary
  $stats = $pdo->query('SELECT status, COUNT(*) as count FROM applications GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Applications | Staff</title>
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

      <div class="header-row" style="display:flex;justify-content:space-between;align-items:end;flex-wrap:wrap">
        <div>
          <h2>Applications</h2>
          <p class="muted">View and manage all submitted applications</p>
        </div>
        <div style="text-align:right;min-width:220px">
          <div style="font-size:13px;color:#888">Summary</div>
          <div style="display:flex;gap:10px;margin-top:2px">
            <div style="background:#f5f5f5;padding:6px 10px;border-radius:6px;font-size:13px">Pending: <b><?= $stats['Pending'] ?? 0 ?></b></div>
            <div style="background:#f5f5f5;padding:6px 10px;border-radius:6px;font-size:13px">Approved: <b><?= $stats['Approved'] ?? 0 ?></b></div>
            <div style="background:#f5f5f5;padding:6px 10px;border-radius:6px;font-size:13px">Completed: <b><?= $stats['Completed'] ?? 0 ?></b></div>
            <div style="background:#f5f5f5;padding:6px 10px;border-radius:6px;font-size:13px">Rejected: <b><?= $stats['Rejected'] ?? 0 ?></b></div>
          </div>
        </div>
      </div>


      <form method="get" style="margin:18px 0 8px 0;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <div>
          <a href="?" class="tab<?= $status_filter=='' ? ' active' : '' ?>">All</a>
          <a href="?status=Pending" class="tab<?= $status_filter=='Pending' ? ' active' : '' ?>">Pending</a>
          <a href="?status=Approved" class="tab<?= $status_filter=='Approved' ? ' active' : '' ?>">Approved</a>
          <a href="?status=Submitted" class="tab<?= $status_filter=='Submitted' ? ' active' : '' ?>">Submitted</a>
          <a href="?status=Completed" class="tab<?= $status_filter=='Completed' ? ' active' : '' ?>">Completed</a>
          <a href="?status=Rejected" class="tab<?= $status_filter=='Rejected' ? ' active' : '' ?>">Rejected</a>
        </div>
        <input type="text" name="applicant" value="<?= htmlspecialchars($applicant_filter) ?>" placeholder="Applicant name" style="padding:6px 12px;border-radius:8px;border:1px solid #ddd;font-size:14px;">
        <input type="text" name="scholarship" value="<?= htmlspecialchars($scholarship_filter) ?>" placeholder="Scholarship title" style="padding:6px 12px;border-radius:8px;border:1px solid #ddd;font-size:14px;">
        <select name="sort" style="padding:6px 12px;border-radius:8px;border:1px solid #ddd;font-size:14px;">
          <option value="created_at"<?= $sort_by=='created_at'?' selected':'' ?>>Sort by Date</option>
          <option value="status"<?= $sort_by=='status'?' selected':'' ?>>Sort by Status</option>
          <option value="scholarship_title"<?= $sort_by=='scholarship_title'?' selected':'' ?>>Sort by Scholarship</option>
          <option value="username"<?= $sort_by=='username'?' selected':'' ?>>Sort by Applicant</option>
        </select>
        <select name="dir" style="padding:6px 12px;border-radius:8px;border:1px solid #ddd;font-size:14px;">
          <option value="desc"<?= $sort_dir=='desc'?' selected':'' ?>>Descending</option>
          <option value="asc"<?= $sort_dir=='asc'?' selected':'' ?>>Ascending</option>
        </select>
        <button type="submit" style="padding:6px 18px;border-radius:8px;background:#b71c1c;color:#fff;border:none;font-size:14px;">Apply</button>
        <style>
          .tab { display:inline-block;padding:6px 18px;margin-right:4px;border-radius:16px;text-decoration:none;color:#444;background:#eee;font-size:14px;transition:background .2s; }
          .tab.active, .tab:hover { background:#b71c1c;color:#fff; }
        </style>
      </form>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <h3>My Applications</h3>
        
        <?php if ($viewingApp): ?>
          <a href="applications.php" style="color:#b91c1c;text-decoration:none;margin-bottom:15px;display:inline-block;font-weight:500">← Back to Applications</a>
          <div style="margin-top:20px;background:#f9f9f9;padding:20px;border-radius:8px">
            <h2><?= htmlspecialchars($viewingApp['scholarship_title']) ?></h2>
            <p style="color:#666;margin-bottom:20px"><?= htmlspecialchars($viewingApp['scholarship_desc'] ?? '') ?></p>
            
            <div style="margin-bottom:20px">
              <strong>Applicant:</strong> <?= htmlspecialchars(($viewingApp['first_name'] ?? '') . ' ' . ($viewingApp['last_name'] ?? '')) ?>
              <br><strong>Email:</strong> <?= htmlspecialchars($viewingApp['email'] ?? '') ?>
            </div>
            
            <div style="margin-bottom:20px">
              <strong>Status:</strong> 
              <?php
                $status = $viewingApp['status'];
                $s = strtolower($status);
                $status_color = ['draft'=>'#999','submitted'=>'#2196F3','pending'=>'#FF9800','under_review'=>'#2196F3','approved'=>'#4CAF50','rejected'=>'#f44336','waitlisted'=>'#FFC107'];
                $color = $status_color[$s] ?? '#999';
              ?>
              <span style="color:<?= $color ?>;font-weight:bold"><?= ucfirst(str_replace('_', ' ', htmlspecialchars(strtolower($status)))) ?></span>
            </div>

            <?php if (in_array($_SESSION['user']['role'] ?? '', ['staff','admin']) && strtolower(($viewingApp['status'] ?? '')) === 'under_review'): ?>
              <div style="margin-top:12px;display:flex;gap:8px">
                <form method="POST" onsubmit="return confirm('Approve this application?');" style="display:inline">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="id" value="<?= (int)$viewingApp['id'] ?>">
                  <input type="hidden" name="new_status" value="Approved">
                  <input type="hidden" name="return_to_view" value="1">
                  <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer">Approve</button>
                </form>
                <form method="POST" onsubmit="return handleReject(this);" style="display:inline">
                  <input type="hidden" name="action" value="change_status">
                  <input type="hidden" name="id" value="<?= (int)$viewingApp['id'] ?>">
                  <input type="hidden" name="new_status" value="Rejected">
                  <input type="hidden" name="reason" value="">
                  <input type="hidden" name="return_to_view" value="1">
                  <button type="submit" style="background:#b91c1c;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer">Reject</button>
                </form>
              </div>
            <?php endif; ?>
            
            <div style="margin-bottom:20px">
              <strong>Submitted:</strong> <?= htmlspecialchars($viewingApp['created_at']) ?>
            </div>
            
            <hr style="margin:20px 0">
            <h4>Application Details</h4>
            <?php if ($viewingApp['motivational_letter']): ?>
              <?php $formData = json_decode($viewingApp['motivational_letter'], true); ?>
              <?php if ($formData): ?>
                <table style="width:100%;border-collapse:collapse">
                  <?php foreach ($formData as $key => $value): ?>
                    <?php if (is_string($value) || is_numeric($value)): ?>
                      <tr style="border-bottom:1px solid #eee">
                        <td style="padding:10px;font-weight:bold;width:30%"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?></td>
                        <td style="padding:10px"><?= htmlspecialchars($value) ?></td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </table>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php else: ?>
      <section class="panel">
        <h3>All Applications</h3>
        <?php if (!$apps): ?>
          <p class="muted">No applications yet.</p>
        <?php else: ?>
          <div style="overflow-x:auto">
            <table class="app-table" style="width:100%;border-collapse:separate;border-spacing:0;margin-top:12px;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(185,28,28,0.08);">
              <thead style="background:#b91c1c;color:#fff">
                <tr>
                  <th style="padding:1rem;border-radius:12px 0 0 0">#</th>
                  <th>Scholarship</th>
                  <th>Applicant</th>
                  <th>Status</th>
                  <th>Document</th>
                  <th>Submitted</th>
                  <th style="border-radius:0 12px 0 0">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($apps as $a): ?>
                  <tr style="border-top:1px solid #eee;transition:background .2s;">
                    <td style="padding:1rem;" data-label="#"><?= htmlspecialchars($a['id']) ?></td>
                    <td style="padding:1rem;" data-label="Scholarship"><?= htmlspecialchars($a['scholarship_title'] ?? '—') ?></td>
                    <td style="padding:1rem;" data-label="Applicant"><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '') ?: ($a['username'] ?? '')) ?></td>
                    <td style="padding:1rem;" data-label="Status">
                      <span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:8px;background:<?= $a['status']=='Approved'?'#e0f7e9':($a['status']=='Pending'?'#fffbe6':($a['status']=='Rejected'?'#fee2e2':'#f3f4f6')) ?>;color:<?= $a['status']=='Approved'?'#16a34a':($a['status']=='Pending'?'#b91c1c':($a['status']=='Rejected'?'#b91c1c':'#444')) ?>;font-weight:600;font-size:0.95rem;" title="Status: <?= htmlspecialchars($a['status']) ?>">
                        <?php if ($a['status']=='Approved'): ?>
                          <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="vertical-align:middle" title="Approved"><circle cx="10" cy="10" r="10" fill="#16a34a"/><path d="M6 10.5l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php elseif ($a['status']=='Pending'): ?>
                          <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="vertical-align:middle" title="Pending"><circle cx="10" cy="10" r="10" fill="#b91c1c"/><path d="M10 5v5l3 3" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php elseif ($a['status']=='Rejected'): ?>
                          <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="vertical-align:middle" title="Rejected"><circle cx="10" cy="10" r="10" fill="#b91c1c"/><path d="M7 7l6 6M13 7l-6 6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <?php else: ?>
                          <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="vertical-align:middle" title="Other"><circle cx="10" cy="10" r="10" fill="#888"/><path d="M10 6v4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="14" r="1" fill="#fff"/></svg>
                        <?php endif; ?>
                        <?= htmlspecialchars($a['status']) ?>
                      </span>
                    </td>
                    <td style="padding:1rem;" data-label="Document">
                      <?php if (!empty($a['document'])): ?>
                        <a href="../<?= htmlspecialchars($a['document']) ?>" target="_blank" style="color:#b91c1c;text-decoration:underline;font-weight:500;display:inline-flex;align-items:center;gap:4px" title="View submitted document">
                          <svg width="16" height="16" viewBox="0 0 20 20" fill="none" style="vertical-align:middle" title="Document"><rect x="3" y="3" width="14" height="14" rx="2" fill="#b91c1c"/><path d="M7 7h6v6H7V7z" fill="#fff"/></svg>
                          View
                        </a>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="padding:1rem;" data-label="Submitted"><small><?= htmlspecialchars($a['created_at']) ?></small></td>
                    <td style="padding:1rem;" data-label="Actions">
                      <a href="applications.php?view=<?= $a['id'] ?>" style="color:#b91c1c;text-decoration:none;font-weight:500;margin-right:8px">View</a>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this application? This action cannot be undone.');">
                        <input type="hidden" name="action" value="delete_application">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" style="background:transparent;border:none;color:#b91c1c;cursor:pointer;font-weight:500;padding:0">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
        <?php endif; ?>
      </section>
      <script>
        function handleReject(form){
          if(!confirm('Reject this application?')) return false;
          var reason = prompt('Optional: provide a reason or comment for rejection (leave blank to skip):','');
          if(reason !== null){
            var inp = form.querySelector('input[name="reason"]');
            if(inp) inp.value = reason;
          }
          return true;
        }
      </script>
    </main>
  </div>
</body>
</html>

