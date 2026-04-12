<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/SurveyHelper.php';

requireLogin();
requireRole('student', 'Student access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

$surveys  = getActiveSurveysForStudent($pdo, $userId);
$activeSurveyId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$activeSurvey   = null;
$questions      = [];

if ($activeSurveyId) {
    $activeSurvey = getSurveyById($pdo, $activeSurveyId);
    if ($activeSurvey && $activeSurvey['status'] === 'active') {
        $questions = getQuestions($pdo, $activeSurveyId);
    } else {
        $activeSurvey = null;
    }
}

$page_title = 'Surveys - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Surveys</h1>
  <p class="text-muted">Share your thoughts through structured surveys</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if ($activeSurvey && !hasResponded($pdo, $activeSurveyId, $userId)): ?>
  <!-- Survey form -->
  <div class="content-card">
    <h2><?= htmlspecialchars($activeSurvey['title']) ?></h2>
    <?php if ($activeSurvey['description']): ?>
      <p class="text-muted"><?= htmlspecialchars($activeSurvey['description']) ?></p>
    <?php endif; ?>
    <form method="POST" action="../controllers/SurveyController.php" style="margin-top:var(--space-xl);">
      <input type="hidden" name="action" value="submit_response">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="survey_id" value="<?= $activeSurveyId ?>">
      <?php foreach($questions as $q): ?>
        <div class="form-group" style="margin-bottom:var(--space-xl);">
          <label style="font-weight:600;">
            <?= htmlspecialchars($q['question']) ?>
            <?php if($q['required']): ?><span style="color:#dc2626;"> *</span><?php endif; ?>
          </label>
          <?php if($q['type'] === 'text'): ?>
            <textarea name="answers[<?= $q['id'] ?>]" class="form-textarea" rows="3" <?= $q['required']?'required':'' ?>></textarea>
          <?php elseif($q['type'] === 'rating_scale'): ?>
            <div style="display:flex;gap:0.5rem;margin-top:var(--space-sm);">
              <?php for($i=1;$i<=5;$i++): ?>
                <label style="display:flex;flex-direction:column;align-items:center;cursor:pointer;gap:4px;">
                  <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $i ?>" <?= $q['required']?'required':'' ?>>
                  <span><?= $i ?></span>
                </label>
              <?php endfor; ?>
            </div>
          <?php elseif($q['type'] === 'multiple_choice' && !empty($q['options'])): ?>
            <?php foreach($q['options'] as $opt): ?>
              <label style="display:flex;align-items:center;gap:var(--space-sm);margin-top:var(--space-sm);cursor:pointer;">
                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= htmlspecialchars($opt) ?>" <?= $q['required']?'required':'' ?>>
                <?= htmlspecialchars($opt) ?>
              </label>
            <?php endforeach; ?>
          <?php elseif($q['type'] === 'yes_no'): ?>
            <div style="display:flex;gap:var(--space-lg);margin-top:var(--space-sm);">
              <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer;">
                <input type="radio" name="answers[<?= $q['id'] ?>]" value="Yes" <?= $q['required']?'required':'' ?>> Yes
              </label>
              <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer;">
                <input type="radio" name="answers[<?= $q['id'] ?>]" value="No" <?= $q['required']?'required':'' ?>> No
              </label>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div style="display:flex;gap:var(--space-md);">
        <a href="surveys.php" class="btn btn-ghost">← Back</a>
        <button type="submit" class="btn btn-primary">Submit Survey</button>
      </div>
    </form>
  </div>
<?php else: ?>
  <!-- Survey list -->
  <div class="content-card">
    <h2>Available Surveys</h2>
    <?php if (!empty($surveys)): ?>
      <table class="modern-table" style="margin-top:var(--space-lg);">
        <thead><tr><th>Survey</th><th>Scholarship</th><th>Cycle</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($surveys as $s): ?>
            <tr>
              <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
              <td><?= htmlspecialchars($s['scholarship_title'] ?? 'All Scholarships') ?></td>
              <td><?= htmlspecialchars($s['cycle_label'] ?? '—') ?></td>
              <td>
                <?php if($s['responded_id']): ?>
                  <span class="status-badge status-approved">Completed</span>
                <?php else: ?>
                  <span class="status-badge status-submitted">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if(!$s['responded_id']): ?>
                  <a href="surveys.php?id=<?= (int)$s['id'] ?>" class="btn btn-primary btn-sm">Answer</a>
                <?php else: ?>
                  <span class="text-muted">Done</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state" style="margin-top:var(--space-xl);">
        <div class="empty-state-icon">📋</div>
        <h3 class="empty-state-title">No Active Surveys</h3>
        <p class="empty-state-description">Check back later for new surveys.</p>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
