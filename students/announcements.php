<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireRole('student', 'Student access required');
$pdo = getPDO();

try {
    $stmt = $pdo->query("SELECT title, message, type, published_at FROM announcements WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY published_at DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $announcements = [];
}

$page_title = 'Announcements - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
</div>

<div class="content-card">
  <?php if (!empty($announcements)): ?>
    <?php foreach ($announcements as $ann): ?>
      <div style="padding:var(--space-lg);border-left:4px solid var(--peach);background:var(--peach-ghost);margin-bottom:var(--space-md);border-radius:var(--r-lg);">
        <h3 style="font-weight:600;margin-bottom:0.4rem;"><?= htmlspecialchars($ann['title']) ?></h3>
        <p style="margin:0.4rem 0;color:var(--gray-700);"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
        <small class="text-muted"><?= date('M d, Y', strtotime($ann['published_at'])) ?></small>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-bullhorn"></i></div>
      <h3 class="empty-state-title">No Announcements</h3>
      <p class="empty-state-description">Check back later for updates.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
