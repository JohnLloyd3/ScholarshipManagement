<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role(['staff', 'admin']);

$pdo = getPDO();

$totalOpenScholarships = $pdo->query('SELECT COUNT(*) FROM scholarships WHERE status = "open"')->fetchColumn();
$totalApplications = $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$pendingApplications = $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','pending')")->fetchColumn();
?>
<?php
$page_title = 'Staff Dashboard - ScholarHub';
$base_path = '../';
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
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">🎓</div>
    <div class="stat-value"><?= htmlspecialchars($totalOpenScholarships) ?></div>
    <div class="stat-label">Open Scholarships</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-value"><?= htmlspecialchars($totalApplications) ?></div>
    <div class="stat-label">Total Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= htmlspecialchars($pendingApplications) ?></div>
    <div class="stat-label">Pending/Submitted</div>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-xl);">Quick Actions</h3>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md);">
    <button class="btn btn-primary" onclick="location.href='../auth/applicant_register.php'">📝 Applicant Registration</button>
    <button class="btn btn-primary" onclick="location.href='post_scholarship.php'">➕ Post Scholarship</button>
    <button class="btn btn-primary" onclick="location.href='scholarships.php'">✏️ Manage Scholarships</button>
    <button class="btn btn-primary" onclick="location.href='applications.php'">👀 View Applications</button>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>

