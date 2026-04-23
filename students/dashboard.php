<?php
/**
 * STUDENT DASHBOARD
 * Role: Student
 * Purpose: Student home � application stats, announcements, open scholarships
 * URL: /students/dashboard.php
 */
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

    // Pending feedback
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications a LEFT JOIN feedback f ON f.application_id = a.id WHERE a.user_id = :uid AND a.status IN ('approved','completed') AND f.id IS NULL");
    $stmt->execute([':uid' => $userId]);
    $pendingFeedback = (int)$stmt->fetchColumn();

    // Recent notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $userId]);
    $recentNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('[students/dashboard] ' . $e->getMessage());
    $applicationsCount = $openScholarships = $unreadNotifs = $approvedCount = $pendingFeedback = 0;
    $recentApps = $announcements = $recentNotifs = [];
}

$page_title = 'Dashboard - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
  <div>
    <h1>Welcome back, <?= htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'Student') ?>!</h1>
  </div>
  <a href="apply_scholarship.php" class="btn btn-primary" style="position:relative;z-index:1;">Apply for Scholarship</a>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon" style="font-size:1.1rem;">&#128221;</div></div>
    <div class="stat-value"><?= $applicationsCount ?></div>
    <div class="stat-label">My Applications</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon" style="font-size:1.1rem;">&#10003;</div></div>
    <div class="stat-value"><?= $approvedCount ?></div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon" style="font-size:1.1rem;"><i class="fas fa-graduation-cap"></i></div></div>
    <div class="stat-value"><?= $openScholarships ?></div>
    <div class="stat-label">Open Scholarships</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon" style="font-size:1.1rem;"><i class="fas fa-bell"></i></div><?php if ($unreadNotifs > 0): ?><span class="stat-trend"><?= $unreadNotifs ?> new</span><?php endif; ?></div>
    <div class="stat-value"><?= $unreadNotifs ?></div>
    <div class="stat-label">Unread Notifications</div>
  </div>
</div>

<!-- Alerts for pending actions -->
<?php if ($pendingFeedback > 0): ?>
<div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
  <?php if ($pendingFeedback > 0): ?>
    <div class="alert alert-warning" style="flex:1;min-width:200px;justify-content:space-between;">
      <span>You have <?= $pendingFeedback ?> application(s) awaiting feedback.</span>
      <a href="feedback.php" class="btn btn-primary btn-sm">Leave Feedback</a>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-bottom:1.5rem;">

  <!-- Announcements -->
  <div class="content-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;">Announcements</h3>
      <a href="announcements.php" class="btn btn-ghost btn-sm">View All &rarr;</a>
    </div>
    <?php if (!empty($announcements)): ?>
      <?php foreach ($announcements as $ann): ?>
        <div style="padding:0.875rem;border-left:3px solid #E53935;background:#FFF5F5;margin-bottom:0.75rem;border-radius:8px;">
          <div style="font-weight:600;font-size:0.875rem;color:#1a1a2e;margin-bottom:0.25rem;"><?= htmlspecialchars($ann['title']) ?></div>
          <div style="font-size:0.8rem;color:#757575;"><?= nl2br(htmlspecialchars(substr($ann['message'], 0, 100))) ?>...</div>
          <div style="font-size:0.75rem;color:#BDBDBD;margin-top:0.35rem;"><?= date('M d, Y', strtotime($ann['published_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state" style="padding:2rem 1rem;">
        <div class="empty-state-description">No announcements yet.</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
