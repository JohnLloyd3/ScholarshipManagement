<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

require_login();
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Mark as seen when viewing (via POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seen_id'])) {
  if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request.';
    header('Location: notifications.php'); exit;
  }
  $sid = (int)($_POST['seen_id'] ?? 0);
  if ($sid > 0) {
    $pdo->prepare('UPDATE notifications SET seen = 1, seen_at = NOW() WHERE id = :id AND user_id = :uid')
      ->execute([':id' => $sid, ':uid' => $user_id]);
    $_SESSION['success'] = 'Notification marked as read.';
  }
  header('Location: notifications.php');
  exit;
}

// Mark all as seen (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_seen'])) {
  if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request.';
    header('Location: notifications.php'); exit;
  }
  $pdo->prepare('UPDATE notifications SET seen = 1, seen_at = NOW() WHERE user_id = :uid')->execute([':uid' => $user_id]);
  $_SESSION['success'] = 'All notifications marked as read.';
  header('Location: notifications.php');
  exit;
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC');
$stmt->execute([':uid' => $user_id]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['seen']) $unreadCount++;
}
?>
<?php
$page_title = 'Notifications - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <div class="flex justify-between items-center">
    <div>
      <h1>🔔 Notifications</h1>
      <p class="text-muted">Stay updated with your scholarship applications</p>
    </div>
    <?php if (count($notifications) > 0): ?>
      <form method="POST">
        <input type="hidden" name="mark_all_seen" value="1">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Mark All as Read</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="content-card">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">🔔</div>
      <h3 class="empty-state-title">No Notifications</h3>
      <p class="empty-state-description">You're all caught up! Check back later for updates.</p>
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: var(--space-md);">
      <?php foreach ($notifications as $n): ?>
        <div class="card <?= $n['seen'] ? '' : 'unread' ?>" style="<?= !$n['seen'] ? 'border-left: 4px solid var(--red-primary); background: var(--red-ghost);' : '' ?>">
          <div class="card-body" style="padding: var(--space-lg);">
            <div class="flex justify-between items-start">
              <div style="flex: 1;">
                <h4 style="font-weight: 600; margin-bottom: 0.5rem; color: var(--gray-900);">
                  <?= htmlspecialchars($n['title']) ?>
                </h4>
                <p style="margin: 0.5rem 0; color: var(--gray-700);">
                  <?= nl2br(htmlspecialchars($n['message'])) ?>
                </p>
                <small class="text-muted">
                  <?= date('M d, Y g:i A', strtotime($n['created_at'])) ?>
                </small>
              </div>
              <?php if (!$n['seen']): ?>
                <form method="POST" style="margin-left: var(--space-md);">
                  <input type="hidden" name="seen_id" value="<?= (int)$n['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">Mark as Read</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
