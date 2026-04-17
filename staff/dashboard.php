<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

requireLogin();
requireAnyRole(['admin', 'staff'], 'Staff access required');

$pdo = getPDO();

try {
    $openScholarships   = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn();
    $totalApplications  = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $pendingApplications = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','pending','under_review')")->fetchColumn();
    $approvedApplications = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();

    // Disbursements
    $pendingDisbursements = (int)$pdo->query("SELECT COUNT(*) FROM disbursements WHERE status = 'pending' AND deleted_at IS NULL")->fetchColumn();
    $totalDisbursed = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'completed' AND deleted_at IS NULL")->fetchColumn();

    // Feedback
    $totalFeedback = (int)$pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
    $avgRating = round((float)$pdo->query("SELECT COALESCE(AVG(rating),0) FROM feedback")->fetchColumn(), 1);

    // Recent applications
    $stmt = $pdo->query("SELECT a.id, a.status, a.created_at, u.first_name, u.last_name, s.title AS scholarship_title FROM applications a JOIN users u ON a.user_id = u.id JOIN scholarships s ON a.scholarship_id = s.id WHERE a.status != 'draft' ORDER BY a.created_at DESC LIMIT 8");
    $recentApps = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('[staff/dashboard] ' . $e->getMessage());
    $openScholarships = $totalApplications = $pendingApplications = $approvedApplications = 0;
    $pendingDisbursements = $totalDisbursed = $totalFeedback = $avgRating = 0;
    $recentApps = [];
}

$page_title = 'Staff Dashboard - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📊 Staff Dashboard</h1>
  <p class="text-muted">Manage scholarships and monitor applications</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-icon">🎓</div>
    <div class="stat-value"><?= $openScholarships ?></div>
    <div class="stat-label">Open Scholarships</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-value"><?= $totalApplications ?></div>
    <div class="stat-label">Total Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= $pendingApplications ?></div>
    <div class="stat-label">Pending Review</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $approvedApplications ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-value">₱<?= number_format($totalDisbursed, 0) ?></div>
    <div class="stat-label">Total Disbursed</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $pendingDisbursements ?></div>
    <div class="stat-label">Pending Disbursements</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div class="stat-value"><?= $avgRating > 0 ? $avgRating : '—' ?></div>
    <div class="stat-label">Avg Feedback Rating</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💬</div>
    <div class="stat-value"><?= $totalFeedback ?></div>
    <div class="stat-label">Feedback Received</div>
  </div>
</div>

<?php
$staffAlerts = [];
if ($pendingApplications > 0) $staffAlerts[] = ['msg' => "$pendingApplications application(s) waiting for review", 'link' => 'applications.php', 'label' => 'Review Now'];
if ($pendingDisbursements > 0) $staffAlerts[] = ['msg' => "$pendingDisbursements disbursement(s) pending processing", 'link' => 'disbursements.php', 'label' => 'Process Now'];
try {
    $underReview = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'under_review'")->fetchColumn();
    if ($underReview > 0) $staffAlerts[] = ['msg' => "$underReview application(s) under review awaiting decision", 'link' => 'applications.php', 'label' => 'Decide Now'];
} catch (Exception $e) {}
?>
<?php if (!empty($staffAlerts)): ?>
<div style="display:flex;flex-direction:column;gap:var(--space-sm);margin-bottom:var(--space-xl);">
  <?php foreach ($staffAlerts as $al): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-md) var(--space-lg);background:#fffbeb;border-left:4px solid #f59e0b;border-radius:var(--radius-lg);">
      <span style="color:#92400e;font-weight:500;">⚠️ <?= htmlspecialchars($al['msg']) ?></span>
      <a href="<?= htmlspecialchars($al['link']) ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars($al['label']) ?></a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:var(--space-xl);margin-bottom:var(--space-xl);">

  <!-- Recent Applications -->
  <div class="content-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);">
      <h2>📝 Recent Applications</h2>
      <a href="applications.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <?php if (!empty($recentApps)): ?>
      <?php foreach ($recentApps as $a): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-sm) 0;border-bottom:1px solid var(--gray-100);">
          <div>
            <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
            <small class="text-muted"><?= htmlspecialchars($a['scholarship_title']) ?></small>
          </div>
          <span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])) ?></span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-muted">No applications yet.</p>
    <?php endif; ?>
  </div>

  <!-- Quick Actions -->
  <div class="content-card">
    <h2 style="margin-bottom:var(--space-lg);">⚡ Quick Actions</h2>
    <div style="display:flex;flex-direction:column;gap:var(--space-md);">
      <a href="post_scholarship.php" class="btn btn-primary" style="text-align:center;">➕ Post Scholarship</a>
      <a href="applications.php" class="btn btn-ghost" style="text-align:center;">📝 Review Applications</a>
      <a href="disbursements.php" class="btn btn-ghost" style="text-align:center;">💰 Record Disbursement <?= $pendingDisbursements > 0 ? "<span style='background:var(--red-primary);color:white;border-radius:999px;padding:1px 7px;font-size:0.75rem;margin-left:4px;'>$pendingDisbursements</span>" : '' ?></a>
      <a href="feedback.php" class="btn btn-ghost" style="text-align:center;">⭐ View Feedback</a>
      <a href="survey_results.php" class="btn btn-ghost" style="text-align:center;">📋 Survey Results</a>
      <a href="reports.php" class="btn btn-ghost" style="text-align:center;">📊 Reports</a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
