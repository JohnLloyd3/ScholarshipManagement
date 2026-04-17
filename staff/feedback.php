<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../helpers/FeedbackHelper.php';

requireLogin();
requireAnyRole(['admin', 'staff'], 'Staff access required');

$pdo          = getPDO();
$scholarships = $pdo->query("SELECT id, title FROM scholarships ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$filterSch    = isset($_GET['scholarship_id']) && $_GET['scholarship_id'] !== '' ? (int)$_GET['scholarship_id'] : null;

$feedback  = getAllFeedback($pdo, $filterSch);
$analytics = getFeedbackAnalytics($pdo, $filterSch);

$page_title = 'Student Feedback - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>⭐ Student Feedback</h1>
  <p class="text-muted">View scholarship experience ratings from students</p>
</div>

<!-- Analytics -->
<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-value"><?= $analytics['total'] ?></div>
    <div class="stat-label">Total Feedback</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $analytics['average'] ?> ★</div>
    <div class="stat-label">Average Rating</div>
  </div>
  <?php for ($i = 5; $i >= 1; $i--): ?>
    <div class="stat-card">
      <div class="stat-value"><?= $analytics['counts'][$i] ?></div>
      <div class="stat-label"><?= $i ?>-Star</div>
    </div>
  <?php endfor; ?>
</div>

<!-- Filter -->
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <form method="GET" style="display:flex;gap:var(--space-md);align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
      <label>Filter by Scholarship</label>
      <select name="scholarship_id" class="form-input" onchange="this.form.submit()">
        <option value="">All Scholarships</option>
        <?php foreach ($scholarships as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterSch === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($filterSch): ?>
      <a href="feedback.php" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Feedback Table -->
<div class="content-card">
  <h2>All Feedback</h2>
  <?php if (!empty($feedback)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead>
        <tr><th>Student</th><th>Scholarship</th><th>Rating</th><th>Comment</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($feedback as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></td>
            <td><?= htmlspecialchars($f['scholarship_title']) ?></td>
            <td>
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <span style="color:<?= $i <= (int)$f['rating'] ? '#f59e0b' : '#d1d5db' ?>;">★</span>
              <?php endfor; ?>
              <small class="text-muted">(<?= (int)$f['rating'] ?>/5)</small>
            </td>
            <td><?= htmlspecialchars($f['comment'] ?? '—') ?></td>
            <td><small><?= date('M d, Y', strtotime($f['submitted_at'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon">⭐</div>
      <h3 class="empty-state-title">No Feedback Yet</h3>
      <p class="empty-state-description">Student feedback will appear here once submitted.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
