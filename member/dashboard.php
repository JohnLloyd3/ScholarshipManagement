<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

$pdo     = getPDO();
$userId  = $_SESSION['user_id'];
$user    = $_SESSION['user'] ?? [];

// Stats
try {
    // Stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = :uid"); $stmt->execute([':uid' => $userId]); $applicationsCount = (int)$stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE status = 'open'"); $openScholarships = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND seen = 0"); $stmt->execute([':uid' => $userId]); $unreadNotifs = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = :uid AND status = 'approved'"); $stmt->execute([':uid' => $userId]); $approvedCount = (int)$stmt->fetchColumn();

    // Recent applications
    $stmt = $pdo->prepare("SELECT a.id, a.status, a.created_at, s.title AS scholarship_title FROM applications a JOIN scholarships s ON a.scholarship_id = s.id WHERE a.user_id = :uid ORDER BY a.created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $userId]);
    $recentApps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Announcements
    $stmt = $pdo->query("SELECT title, message, type, published_at FROM announcements WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY published_at DESC LIMIT 3");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pending surveys
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM surveys s LEFT JOIN survey_responses sr ON sr.survey_id = s.id AND sr.user_id = :uid WHERE s.status = 'active' AND sr.id IS NULL AND (s.scholarship_id IS NULL OR s.scholarship_id IN (SELECT scholarship_id FROM applications WHERE user_id = :uid2 AND status IN ('approved','completed')))");
    $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
    $pendingSurveys = (int)$stmt->fetchColumn();

    // Pending feedback
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a LEFT JOIN feedback f ON f.application_id = a.id WHERE a.user_id = :uid AND a.status IN ('approved','completed') AND f.id IS NULL");
    $stmt->execute([':uid' => $userId]);
    $pendingFeedback = (int)$stmt->fetchColumn();

} catch (Exception $e) {
    error_log('[member/dashboard] ' . $e->getMessage());
    $applicationsCount = $openScholarships = $unreadNotifs = $approvedCount = $pendingSurveys = $pendingFeedback = 0;
    $recentApps = $announcements = [];
}

$page_title = 'Dashboard - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
  <div>
    <h1>👋 Welcome back, <?= htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'Student') ?>!</h1>
    <p class="text-muted">Here's what's happening with your scholarships</p>
  </div>
  <a href="apply_scholarship.php" class="btn btn-primary">Apply for Scholarship</a>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-value"><?= $applicationsCount ?></div>
    <div class="stat-label">My Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $approvedCount ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🎓</div>
    <div class="stat-value"><?= $openScholarships ?></div>
    <div class="stat-label">Open Scholarships</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🔔</div>
    <div class="stat-value"><?= $unreadNotifs ?></div>
    <div class="stat-label">Unread Notifications</div>
  </div>
</div>

<!-- Alerts for pending actions -->
<?php if ($pendingSurveys > 0 || $pendingFeedback > 0): ?>
<div style="display:flex;gap:var(--space-md);margin-bottom:var(--space-xl);flex-wrap:wrap;">
  <?php if ($pendingSurveys > 0): ?>
    <div class="alert alert-warning" style="flex:1;min-width:200px;display:flex;justify-content:space-between;align-items:center;">
      <span>📋 You have <?= $pendingSurveys ?> pending survey(s) to complete.</span>
      <a href="surveys.php" class="btn btn-primary btn-sm">Answer Now</a>
    </div>
  <?php endif; ?>
  <?php if ($pendingFeedback > 0): ?>
    <div class="alert alert-warning" style="flex:1;min-width:200px;display:flex;justify-content:space-between;align-items:center;">
      <span>⭐ You have <?= $pendingFeedback ?> application(s) awaiting feedback.</span>
      <a href="feedback.php" class="btn btn-primary btn-sm">Leave Feedback</a>
    </div>
  <?php endif; ?>
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
        <div style="display:flex;justify-content:space-between;align-items:center;padding:var(--space-md) 0;border-bottom:1px solid var(--gray-100);">
          <div>
            <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($a['scholarship_title']) ?></div>
            <small class="text-muted"><?= date('M d, Y', strtotime($a['created_at'])) ?></small>
          </div>
          <span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])) ?></span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-muted">No applications yet. <a href="scholarships.php">Browse scholarships</a> to get started.</p>
    <?php endif; ?>
  </div>

  <!-- Quick Actions -->
  <div class="content-card">
    <h2 style="margin-bottom:var(--space-lg);">⚡ Quick Actions</h2>
    <div style="display:flex;flex-direction:column;gap:var(--space-md);">
      <a href="scholarships.php" class="btn btn-primary" style="text-align:center;">🎓 Browse Scholarships</a>
      <a href="applications.php" class="btn btn-ghost" style="text-align:center;">📝 My Applications</a>
      <a href="interview_booking.php" class="btn btn-ghost" style="text-align:center;">📅 Interview Booking</a>
      <a href="payouts.php" class="btn btn-ghost" style="text-align:center;">💰 My Payouts</a>
      <a href="surveys.php" class="btn btn-ghost" style="text-align:center;">📋 Surveys <?= $pendingSurveys > 0 ? "<span style='background:var(--red-primary);color:white;border-radius:999px;padding:1px 7px;font-size:0.75rem;margin-left:4px;'>$pendingSurveys</span>" : '' ?></a>
      <a href="feedback.php" class="btn btn-ghost" style="text-align:center;">⭐ Feedback <?= $pendingFeedback > 0 ? "<span style='background:var(--red-primary);color:white;border-radius:999px;padding:1px 7px;font-size:0.75rem;margin-left:4px;'>$pendingFeedback</span>" : '' ?></a>
    </div>
  </div>

</div>

<!-- Announcements -->
<?php if (!empty($announcements)): ?>
<div class="content-card">
  <h2 style="margin-bottom:var(--space-lg);">📢 Announcements</h2>
  <?php foreach ($announcements as $ann): ?>
    <div style="padding:var(--space-lg);border-left:4px solid var(--red-primary);background:var(--red-ghost);margin-bottom:var(--space-md);border-radius:var(--radius-lg);">
      <h4 style="font-weight:600;margin-bottom:0.4rem;"><?= htmlspecialchars($ann['title']) ?></h4>
      <p style="margin:0.4rem 0;color:var(--gray-700);"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
      <small class="text-muted"><?= date('M d, Y', strtotime($ann['published_at'])) ?></small>
    </div>
  <?php endforeach; ?>
  <div style="margin-top:var(--space-md);text-align:right;">
    <a href="announcements.php" class="btn btn-ghost btn-sm">View All Announcements →</a>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
