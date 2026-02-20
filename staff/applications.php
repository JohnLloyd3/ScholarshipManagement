<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role(['staff', 'admin']);

$pdo = getPDO();


// Status filter logic

$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$applicant_filter = $_GET['applicant'] ?? '';
$scholarship_filter = $_GET['scholarship'] ?? '';
$allowed_sort = ['created_at','status','scholarship_title','username'];
$allowed_dir = ['asc','desc'];
$params = [];
$sql = 'SELECT a.*, u.username, u.first_name, u.last_name, s.title AS scholarship_title FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN scholarships s ON a.scholarship_id = s.id';
$where = [];
if ($status_filter && in_array($status_filter, ['Pending', 'Approved', 'Completed', 'Rejected'])) {
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

      <section class="panel" style="box-shadow:0 6px 24px rgba(185,28,28,0.08);border-radius:16px;">
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
                  <th style="border-radius:0 12px 0 0">Submitted</th>
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
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>

