<?php
/**
 * ADMIN DASHBOARD
 * Role: Admin
 * Purpose: Overview of system stats — students, scholarships, applications, disbursements
 * URL: /admin/dashboard.php
 */
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

startSecureSession();
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();

try {
    $totalStudents       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totalScholarships   = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn();
    $pendingApplications = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','pending','under_review')")->fetchColumn();
    $approvedCount       = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();
    $rejectedCount       = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'rejected'")->fetchColumn();
    $totalUsers          = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    $stmt = $pdo->query('SELECT a.id, a.status, a.created_at, u.first_name, u.last_name, s.title as scholarship_title
                         FROM applications a
                         LEFT JOIN users u ON a.user_id = u.id
                         LEFT JOIN scholarships s ON a.scholarship_id = s.id
                         ORDER BY a.created_at DESC LIMIT 12');
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    $totalStudents = $totalScholarships = $pendingApplications = $approvedCount = $rejectedCount = $totalUsers = 0;
    $recent = [];
}

$adminName = trim(($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? '')) ?: 'Admin';

$page_title = 'Admin Dashboard - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<!-- Welcome Banner -->
<div class="page-header">
  <div>
    <h1>Welcome, <?= htmlspecialchars($adminName) ?>!</h1>
    <p>Admin Dashboard</p>
  </div>

</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-top">
      <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
    </div>
    <div class="stat-value"><?= number_format($totalStudents) ?></div>
    <div class="stat-label">Registered Students</div>
    <div class="stat-sub">vs. last semester</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div class="stat-icon">📚</div>
    </div>
    <div class="stat-value"><?= number_format($totalScholarships) ?></div>
    <div class="stat-label">Scholarships</div>
    <div class="stat-sub">active programs</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
    </div>
    <div class="stat-value"><?= number_format($pendingApplications) ?></div>
    <div class="stat-label">Pending Applications</div>
    <div class="stat-sub">awaiting review</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div class="stat-icon">✅</div>
    </div>
    <div class="stat-value"><?= number_format($approvedCount) ?></div>
    <div class="stat-label">Approved</div>
    <div class="stat-sub">this academic year</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div class="stat-icon"><i class="fas fa-times"></i></div>
    </div>
    <div class="stat-value"><?= number_format($rejectedCount) ?></div>
    <div class="stat-label">Rejected</div>
    <div class="stat-sub">this academic year</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div class="stat-icon"><i class="fas fa-users"></i></div>
    </div>
    <div class="stat-value"><?= number_format($totalUsers) ?></div>
    <div class="stat-label">Total Users</div>
    <div class="stat-sub">all roles combined</div>
  </div>
</div>


<!-- Recent Applications -->
<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h3 style="margin:0;">Recent Applications</h3>
    <a href="applications.php" class="btn btn-secondary btn-sm">View All</a>
  </div>

  <?php if (empty($recent)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-file-alt"></i></div>
      <div class="empty-state-title">No Applications Yet</div>
      <div class="empty-state-description">Applications will appear here once students start applying.</div>
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
            <td><?= htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
            <td><?= htmlspecialchars($r['scholarship_title'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= strtolower($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td style="color:#9E9E9E;font-size:0.8rem;"><?= htmlspecialchars(date('M d, Y', strtotime($r['created_at']))) ?></td>
            <td>
              <form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Delete this application?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑 Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
