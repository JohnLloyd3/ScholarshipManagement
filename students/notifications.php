<?php
/**
 * STUDENT — NOTIFICATIONS
 * Role: Student
 * Purpose: View and mark as read all system notifications for the student
 * URL: /students/notifications.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];

// Mark single notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seen_id'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request.';
        header('Location: notifications.php'); exit;
    }
    $sid = (int)($_POST['seen_id'] ?? 0);
    if ($sid > 0) {
        $pdo->prepare('UPDATE notifications SET seen = 1, seen_at = NOW() WHERE id = :id AND user_id = :uid')
            ->execute([':id' => $sid, ':uid' => $userId]);
        $_SESSION['success'] = 'Notification marked as read.';
    }
    header('Location: notifications.php'); exit;
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_seen'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request.';
        header('Location: notifications.php'); exit;
    }
    $pdo->prepare('UPDATE notifications SET seen = 1, seen_at = NOW() WHERE user_id = :uid')
        ->execute([':uid' => $userId]);
    $_SESSION['success'] = 'All notifications marked as read.';
    header('Location: notifications.php'); exit;
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC');
$stmt->execute([':uid' => $userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$unreadCount   = count(array_filter($notifications, fn($n) => !$n['seen']));

$page_title = 'Notifications - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
  <div>
    <h1><i class="fas fa-bell"></i> Notifications</h1>
    <p class="text-muted"><?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?></p>
  </div>
  <?php if ($unreadCount > 0): ?>
    <form method="POST">
      <input type="hidden" name="mark_all_seen" value="1">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <button type="submit" class="btn btn-ghost btn-sm">✓ Mark All as Read</button>
    </form>
  <?php endif; ?>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-bell"></i></div>
      <h3 class="empty-state-title">No Notifications</h3>
      <p class="empty-state-description">You're all caught up! Check back later for updates.</p>
    </div>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:var(--space-md);">
      <?php foreach ($notifications as $n): ?>
        <div style="padding:var(--space-lg);border-radius:var(--r-lg);border:1px solid var(--gray-200);<?= !$n['seen'] ? 'border-left:4px solid var(--peach);background:var(--peach-ghost);' : '' ?>display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-md);">
          <div style="flex:1;">
            <h4 style="font-weight:600;margin-bottom:0.4rem;color:var(--gray-900);"><?= htmlspecialchars($n['title']) ?></h4>
            <p style="margin:0.4rem 0;color:var(--gray-700);"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
            <small class="text-muted"><?= date('M d, Y g:i A', strtotime($n['created_at'])) ?></small>
          </div>
          <?php if (!$n['seen']): ?>
            <form method="POST" style="flex-shrink:0;">
              <input type="hidden" name="seen_id" value="<?= (int)$n['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Mark Read</button>
            </form>
          <?php else: ?>
            <span class="text-muted" style="font-size:0.8rem;flex-shrink:0;">✓ Read</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
