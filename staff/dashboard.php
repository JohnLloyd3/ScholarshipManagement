<?php
/**
 * STAFF DASHBOARD
 * Role: Staff / Admin
 * Purpose: Staff overview — applications, disbursements, feedback stats
 * URL: /staff/dashboard.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireAnyRole(['staff', 'admin'], 'Staff access required');

$pdo = getPDO();

try {
    // Basic stats
    $openScholarships   = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'")->fetchColumn();
    $totalApplications  = (int)$pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $pendingApplications = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','pending','under_review')")->fetchColumn();
    $approvedApplications = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'approved'")->fetchColumn();

    // Disbursements - check if deleted_at column exists
    $hasDeletedAt = false;
    try {
        $hasDeletedAt = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'deleted_at'")->fetch();
    } catch (Exception $e) {
        error_log('[staff/dashboard] Column check error: ' . $e->getMessage());
    }
    
    if ($hasDeletedAt) {
        $pendingDisbursements = (int)$pdo->query("SELECT COUNT(*) FROM disbursements WHERE status = 'pending' AND deleted_at IS NULL")->fetchColumn();
        $totalDisbursed = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'completed' AND deleted_at IS NULL")->fetchColumn();
    } else {
        $pendingDisbursements = (int)$pdo->query("SELECT COUNT(*) FROM disbursements WHERE status = 'pending'")->fetchColumn();
        $totalDisbursed = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE status = 'completed'")->fetchColumn();
    }

    // Feedback stats
    try {
        $totalFeedback = (int)$pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
        $avgRating = round((float)$pdo->query("SELECT COALESCE(AVG(rating),0) FROM feedback")->fetchColumn(), 1);
    } catch (Exception $e) {
        error_log('[staff/dashboard] Feedback stats error: ' . $e->getMessage());
        $totalFeedback = 0;
        $avgRating = 0;
    }
    
    // Interview stats - NEW SYSTEM
    try {
        $totalInterviewSessions = (int)$pdo->query("SELECT COUNT(*) FROM interview_sessions")->fetchColumn();
        $totalInterviewAssignments = (int)$pdo->query("SELECT COUNT(*) FROM interview_assignments")->fetchColumn();
        $upcomingInterviews = (int)$pdo->query("SELECT COUNT(*) FROM interview_sessions WHERE session_date >= CURDATE()")->fetchColumn();
    } catch (Exception $e) {
        error_log('[staff/dashboard] Interview stats error: ' . $e->getMessage());
        $totalInterviewSessions = 0;
        $totalInterviewAssignments = 0;
        $upcomingInterviews = 0;
    }

    // Recent applications
    try {
        $stmt = $pdo->query("SELECT a.id, a.status, a.created_at, u.first_name, u.last_name, s.title AS scholarship_title 
                             FROM applications a 
                             JOIN users u ON a.user_id = u.id 
                             JOIN scholarships s ON a.scholarship_id = s.id 
                             WHERE a.status != 'draft' 
                             ORDER BY a.created_at DESC 
                             LIMIT 8");
        $recentApps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[staff/dashboard] Recent apps error: ' . $e->getMessage());
        $recentApps = [];
    }

} catch (Exception $e) {
    error_log('[staff/dashboard] Critical error: ' . $e->getMessage());
    $openScholarships = $totalApplications = $pendingApplications = $approvedApplications = 0;
    $pendingDisbursements = $totalDisbursed = $totalFeedback = $avgRating = 0;
    $totalInterviewSessions = $totalInterviewAssignments = $upcomingInterviews = 0;
    $recentApps = [];
}

$staffName = trim(($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? '')) ?: 'Staff';

$page_title = 'Staff Dashboard - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<!-- Welcome Banner -->
<div class="page-header">
  <div>
    <h1>Welcome, <?= htmlspecialchars($staffName) ?>!</h1>
    <p>Staff Dashboard</p>
  </div>

</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid stats-grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-graduation-cap"></i></div></div>
    <div class="stat-value"><?= number_format($openScholarships) ?></div>
    <div class="stat-label">Open Scholarships</div>
    <div class="stat-sub">active programs</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-file-alt"></i></div></div>
    <div class="stat-value"><?= number_format($totalApplications) ?></div>
    <div class="stat-label">Total Applications</div>
    <div class="stat-sub">all time</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div><span class="stat-trend <?= $pendingApplications > 0 ? 'down' : '' ?>"><?= $pendingApplications > 0 ? 'needs review' : 'all clear' ?></span></div>
    <div class="stat-value"><?= number_format($pendingApplications) ?></div>
    <div class="stat-label">Pending Review</div>
    <div class="stat-sub">awaiting action</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
    <div class="stat-value"><?= number_format($approvedApplications) ?></div>
    <div class="stat-label">Approved</div>
    <div class="stat-sub">this academic year</div>
  </div>
</div>

<div class="stats-grid stats-grid-4" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div></div>
    <div class="stat-value">₱<?= number_format($totalDisbursed, 0) ?></div>
    <div class="stat-label">Total Disbursed</div>
    <div class="stat-sub">completed payments</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div><span class="stat-trend <?= $pendingDisbursements > 0 ? 'down' : '' ?>"><?= $pendingDisbursements > 0 ? 'pending' : 'all clear' ?></span></div>
    <div class="stat-value"><?= number_format($pendingDisbursements) ?></div>
    <div class="stat-label">Pending Disbursements</div>
    <div class="stat-sub">awaiting processing</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-calendar-alt"></i></div></div>
    <div class="stat-value"><?= number_format($totalInterviewSessions) ?></div>
    <div class="stat-label">Interview Sessions</div>
    <div class="stat-sub"><?= $upcomingInterviews ?> upcoming</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-users"></i></div></div>
    <div class="stat-value"><?= number_format($totalInterviewAssignments) ?></div>
    <div class="stat-label">Interview Assignments</div>
    <div class="stat-sub">total assigned</div>
  </div>
</div>

<!-- Alerts -->
<?php
$staffAlerts = [];
if ($pendingApplications > 0) $staffAlerts[] = ['msg' => "$pendingApplications application(s) waiting for review", 'link' => 'applications.php', 'label' => 'Review Now', 'type' => 'warning'];
if ($pendingDisbursements > 0) $staffAlerts[] = ['msg' => "$pendingDisbursements disbursement(s) pending processing", 'link' => 'disbursements.php', 'label' => 'Process Now', 'type' => 'warning'];
try {
    $underReview = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'under_review'")->fetchColumn();
    if ($underReview > 0) $staffAlerts[] = ['msg' => "$underReview application(s) under review awaiting decision", 'link' => 'applications.php', 'label' => 'Decide Now', 'type' => 'info'];
} catch (Exception $e) {}
?>
<?php foreach ($staffAlerts as $al): ?>
  <div class="alert alert-<?= $al['type'] ?>" style="justify-content:space-between;">
    <span><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($al['msg']) ?></span>
    <a href="<?= htmlspecialchars($al['link']) ?>" class="btn btn-primary btn-sm"><?= htmlspecialchars($al['label']) ?></a>
  </div>
<?php endforeach; ?>

<!-- Recent Applications -->
<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h3 style="margin:0;">Recent Applications</h3>
    <a href="applications.php" class="btn btn-secondary btn-sm">View All</a>
  </div>
  <?php if (!empty($recentApps)): ?>
    <table class="modern-table">
      <thead>
        <tr><th>Applicant</th><th>Scholarship</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentApps as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
            <td><?= htmlspecialchars($a['scholarship_title']) ?></td>
            <td><span class="status-badge status-<?= strtolower($a['status']) ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])) ?></span></td>
            <td style="color:#9E9E9E;font-size:0.8rem;"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
            <td>
              <a href="application_view.php?id=<?= (int)$a['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-eye"></i> View Details
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-file-alt"></i></div>
      <div class="empty-state-title">No Applications Yet</div>
      <div class="empty-state-description">Applications will appear here once students start applying.</div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
