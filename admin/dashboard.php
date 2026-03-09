<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();

try {
    // Stats
    $totalApplications = (int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn() ?: 0;
    // Treat submitted / pending / under_review as pending workload
    $pendingApplications = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','pending','under_review')")->fetchColumn() ?: 0;
    $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0;
    $totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn() ?: 0;
    $totalScholarships = (int) $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn() ?: 0;
    $approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn() ?: 0;
    $rejectedCount = (int) $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'")->fetchColumn() ?: 0;

    // Recent applications
    $stmt = $pdo->query('SELECT a.id, a.title, a.status, a.created_at, a.submitted_at, u.first_name, u.last_name, s.title as scholarship_title 
                         FROM applications a 
                         LEFT JOIN users u ON a.user_id = u.id 
                         LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                         ORDER BY a.created_at DESC LIMIT 12');
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    $totalApplications = 0;
    $pendingApplications = 0;
    $pendingReviews = 0;
    $totalUsers = 0;
    $totalScholarships = 0;
    $recent = [];
}
?>
<?php
$page_title = 'Admin Dashboard - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>⚙️ Admin Dashboard</h1>
  <p class="text-muted">System overview and management</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= htmlspecialchars($totalStudents) ?></div>
    <div class="stat-label">Registered Students</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🎓</div>
    <div class="stat-value"><?= htmlspecialchars($totalScholarships) ?></div>
    <div class="stat-label">Scholarships Available</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= htmlspecialchars($pendingApplications) ?></div>
    <div class="stat-label">Pending Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= htmlspecialchars($approvedCount) ?></div>
    <div class="stat-label">Approved Scholars</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">❌</div>
    <div class="stat-value"><?= htmlspecialchars($rejectedCount) ?></div>
    <div class="stat-label">Rejected Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👤</div>
    <div class="stat-value"><?= htmlspecialchars($totalUsers) ?></div>
    <div class="stat-label">Total Users</div>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-lg);">Master Controls</h3>
  <p class="text-muted" style="margin-bottom: var(--space-xl);">Quick access to manage every part of the system.</p>
  <div style="display: flex; gap: var(--space-md); flex-wrap: wrap;">
    <a class="btn btn-primary" href="users.php">👥 Manage Users</a>
    <a class="btn btn-primary" href="scholarships.php">🎓 Manage Scholarships</a>
    <a class="btn btn-primary" href="applications.php">📝 Manage Applications</a>
    <a class="btn btn-primary" href="analytics.php">📊 Analytics</a>
    <a class="btn btn-primary" href="activity_logs.php">📋 Activity Logs</a>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-xl);">Recent Applications</h3>
  <?php if (empty($recent)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📝</div>
      <h3 class="empty-state-title">No Applications Yet</h3>
      <p class="empty-state-description">Applications will appear here once students start applying.</p>
    </div>
  <?php else: ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Applicant</th>
          <th>Scholarship</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['id']) ?></td>
            <td><?= htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?></td>
            <td><?= htmlspecialchars($r['scholarship_title'] ?? $r['title']) ?></td>
            <td><span class="status-badge status-<?= strtolower($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
            <td>
              <form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Delete this application?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">🗑️ Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>