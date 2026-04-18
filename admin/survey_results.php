<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../helpers/SurveyHelper.php';

requireLogin();
requireAnyRole(['admin'], 'Admin access required');

$pdo      = getPDO();
$userId   = $_SESSION['user_id'];
$surveys  = getAllSurveys($pdo);
$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$survey   = $surveyId ? getSurveyById($pdo, $surveyId) : null;

if (!$survey && $surveyId) {
    $_SESSION['flash'] = 'Survey not found.';
    header('Location: surveys.php');
    exit;
}

$responses = $survey ? getResponses($pdo, $surveyId) : [];
$analytics = $survey ? getSurveyAnalytics($pdo, $surveyId) : [];

// ── CSV Export ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $survey) {
    $questions = getQuestions($pdo, $surveyId);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="survey_' . $surveyId . '_results_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');

    // Header row: metadata + one column per question
    $headers = ['Response ID', 'Student Name', 'Application ID', 'Submitted At'];
    foreach ($questions as $q) {
        $headers[] = $q['question'];
    }
    fputcsv($out, $headers);

    // Data rows
    foreach ($responses as $r) {
        $row = [
            $r['id'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['application_id'],
            $r['submitted_at'],
        ];
        // Map answers by question_id for ordered output
        $answerMap = [];
        foreach ($r['answers'] as $ans) {
            $answerMap[$ans['question_id']] = $ans['answer'];
        }
        foreach ($questions as $q) {
            $row[] = $answerMap[$q['id']] ?? '';
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$page_title = 'Survey Results - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
  <div>
    <h1>📊 Survey Results</h1>
    <p class="text-muted"><?= $survey ? htmlspecialchars($survey['title']) . ' &mdash; ' . count($responses) . ' response(s)' : 'Select a survey to view results' ?></p>
  </div>
  <a href="surveys.php" class="btn btn-ghost">← Back</a>
</div>

<!-- Survey selector -->
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <form method="GET" style="display:flex;gap:var(--space-md);align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;min-width:240px;">
      <label>Select Survey</label>
      <select name="id" class="form-input" onchange="this.form.submit()">
        <option value="">— Choose a survey —</option>
        <?php foreach ($surveys as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $surveyId === (int)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['title']) ?> (<?= ucfirst($s['status']) ?>, <?= (int)$s['response_count'] ?> responses)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($survey && !empty($responses)): ?>
      <a href="survey_results.php?id=<?= $surveyId ?>&export=csv" class="btn btn-primary">📥 Export CSV</a>
    <?php endif; ?>
  </form>
</div>

<?php if (!$survey): ?>
  <div class="content-card">
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3 class="empty-state-title">Select a Survey</h3>
      <p class="empty-state-description">Choose a survey above to view its results and analytics.</p>
    </div>
  </div>
<?php else: ?>

<!-- Analytics Summary -->
<?php if (!empty($analytics)): ?>
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <h2>📈 Analytics Summary</h2>
  <?php foreach ($analytics as $a): ?>
    <div style="border-bottom:1px solid var(--gray-200);padding:var(--space-lg) 0;">
      <p style="font-weight:600;margin-bottom:var(--space-sm);"><?= htmlspecialchars($a['question']) ?></p>
      <?php if ($a['type'] === 'rating_scale'): ?>
        <p class="text-muted">Average rating: <strong><?= $a['average'] ?> / 5</strong> (<?= count($a['answers']) ?> responses)</p>
        <div style="display:flex;gap:0.3rem;margin-top:var(--space-sm);">
          <?php for($i=1;$i<=5;$i++): ?>
            <span style="color:<?= $i<=$a['average']?'#f59e0b':'#d1d5db' ?>;font-size:1.5rem;">★</span>
          <?php endfor; ?>
        </div>
      <?php elseif ($a['type'] === 'multiple_choice' && !empty($a['counts'])): ?>
        <?php $total = array_sum($a['counts']); ?>
        <?php foreach ($a['counts'] as $opt => $cnt): ?>
          <div style="margin-bottom:var(--space-sm);">
            <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
              <span><?= htmlspecialchars($opt) ?></span>
              <span class="text-muted"><?= $cnt ?> (<?= $total > 0 ? round($cnt/$total*100) : 0 ?>%)</span>
            </div>
            <div style="background:var(--gray-200);border-radius:4px;height:8px;">
              <div style="background:var(--peach);height:8px;border-radius:4px;width:<?= $total > 0 ? round($cnt/$total*100) : 0 ?>%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted"><?= count($a['answers']) ?> text response(s)</p>
        <?php foreach (array_slice($a['answers'], 0, 5) as $ans): ?>
          <blockquote style="border-left:3px solid var(--gray-300);padding-left:var(--space-md);margin:var(--space-sm) 0;color:var(--gray-600);font-style:italic;">
            <?= htmlspecialchars($ans) ?>
          </blockquote>
        <?php endforeach; ?>
        <?php if (count($a['answers']) > 5): ?>
          <p class="text-muted"><small>...and <?= count($a['answers']) - 5 ?> more</small></p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Individual Responses -->
<div class="content-card">
  <h2>📋 Individual Responses</h2>
  <?php if (!empty($responses)): ?>
    <?php foreach ($responses as $r): ?>
      <div style="border:1px solid var(--gray-200);border-radius:var(--r-lg);padding:var(--space-lg);margin-top:var(--space-lg);">
        <div style="display:flex;justify-content:space-between;margin-bottom:var(--space-md);">
          <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
          <small class="text-muted"><?= date('M d, Y H:i', strtotime($r['submitted_at'])) ?></small>
        </div>
        <?php foreach ($r['answers'] as $ans): ?>
          <div style="margin-bottom:var(--space-sm);">
            <span class="text-muted" style="font-size:0.85rem;"><?= htmlspecialchars($ans['question']) ?></span><br>
            <span><?= htmlspecialchars($ans['answer']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon">📊</div>
      <h3 class="empty-state-title">No Responses Yet</h3>
      <p class="empty-state-description">Responses will appear here once students submit.</p>
    </div>
  <?php endif; ?>
</div>

<?php endif; // end survey selected ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
