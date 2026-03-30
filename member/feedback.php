<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/FeedbackHelper.php';

requireLogin();
requireRole('student', 'Student access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

$pending  = getEligibleFeedbackApplications($pdo, $userId);
$submitted = getStudentFeedback($pdo, $userId);

$page_title = 'My Feedback - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>⭐ Feedback</h1>
  <p class="text-muted">Rate your scholarship experience</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Pending feedback -->
<?php if (!empty($pending)): ?>
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <h2>📝 Leave Feedback</h2>
  <?php foreach($pending as $app): ?>
    <div style="border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:var(--space-lg);margin-top:var(--space-lg);">
      <h3 style="margin:0 0 var(--space-md) 0;"><?= htmlspecialchars($app['scholarship_title']) ?></h3>
      <form method="POST" action="../controllers/FeedbackController.php">
        <input type="hidden" name="action" value="submit_feedback">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
        <div class="form-group">
          <label>Rating *</label>
          <div class="star-picker" style="display:flex;gap:0.5rem;font-size:2rem;cursor:pointer;" data-name="rating_<?= $app['id'] ?>">
            <?php for($i=1;$i<=5;$i++): ?>
              <span class="star" data-val="<?= $i ?>" style="color:#d1d5db;transition:color 0.15s;" onclick="setRating(this)">★</span>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="rating_<?= $app['id'] ?>" required>
        </div>
        <div class="form-group">
          <label>Comment (optional)</label>
          <textarea name="comment" class="form-textarea" rows="3" placeholder="Share your experience..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit Feedback</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Submitted feedback -->
<div class="content-card">
  <h2>✅ Submitted Feedback</h2>
  <?php if (!empty($submitted)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead><tr><th>Scholarship</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach($submitted as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['scholarship_title']) ?></td>
            <td>
              <?php for($i=1;$i<=5;$i++): ?>
                <span style="color:<?= $i<=(int)$f['rating']?'#f59e0b':'#d1d5db' ?>;">★</span>
              <?php endfor; ?>
              <small class="text-muted">(<?= (int)$f['rating'] ?>/5)</small>
            </td>
            <td><?= htmlspecialchars($f['comment'] ?? '—') ?></td>
            <td><small><?= date('M d, Y', strtotime($f['submitted_at'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon">⭐</div>
      <h3 class="empty-state-title">No Feedback Yet</h3>
      <p class="empty-state-description">Your submitted feedback will appear here.</p>
    </div>
  <?php endif; ?>
</div>

<script>
function setRating(star) {
  const val = parseInt(star.dataset.val);
  const picker = star.closest('.star-picker');
  const name = picker.dataset.name;
  document.getElementById(name).value = val;
  picker.querySelectorAll('.star').forEach((s, i) => {
    s.style.color = i < val ? '#f59e0b' : '#d1d5db';
  });
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
