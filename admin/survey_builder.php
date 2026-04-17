<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../helpers/SurveyHelper.php';

requireLogin();
requireAnyRole(['admin'], 'Admin access required');

$pdo      = getPDO();
$surveyId = (int)($_GET['id'] ?? 0);
$survey   = $surveyId ? getSurveyById($pdo, $surveyId) : null;

if (!$survey) {
    $_SESSION['flash'] = 'Survey not found.';
    header('Location: surveys.php');
    exit;
}

$questions    = getQuestions($pdo, $surveyId);
$scholarships = $pdo->query("SELECT id, title FROM scholarships ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$csrf_token   = generateCSRFToken();

$page_title = 'Survey Builder - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
  <div>
    <h1>🔧 Survey Builder</h1>
    <p class="text-muted"><?= htmlspecialchars($survey['title']) ?> &mdash; <span class="status-badge status-<?= $survey['status'] ?>"><?= ucfirst($survey['status']) ?></span></p>
  </div>
  <a href="surveys.php" class="btn btn-ghost">← Back to Surveys</a>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if ($survey['status'] !== 'draft'): ?>
  <div class="alert alert-warning">⚠️ This survey is <strong><?= $survey['status'] ?></strong>. Questions cannot be edited.</div>
<?php endif; ?>

<div class="content-card">
  <form method="POST" action="../controllers/SurveyController.php" id="builderForm">
    <input type="hidden" name="action" value="save_questions">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="survey_id" value="<?= $surveyId ?>">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-xl);">
      <h2>Questions</h2>
      <?php if ($survey['status'] === 'draft'): ?>
        <button type="button" onclick="addQuestion()" class="btn btn-primary">➕ Add Question</button>
      <?php endif; ?>
    </div>

    <div id="questionsContainer">
      <?php foreach ($questions as $i => $q): ?>
        <div class="question-block" data-index="<?= $i ?>" style="border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:var(--space-lg);margin-bottom:var(--space-lg);background:var(--gray-50);">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-md);">
            <div style="flex:1;">
              <div class="form-group">
                <label>Question *</label>
                <input type="text" name="questions[<?= $i ?>][question]" class="form-input" value="<?= htmlspecialchars($q['question']) ?>" required <?= $survey['status'] !== 'draft' ? 'disabled' : '' ?>>
              </div>
              <div style="display:flex;gap:var(--space-md);">
                <div class="form-group" style="flex:1;">
                  <label>Type</label>
                  <select name="questions[<?= $i ?>][type]" class="form-input q-type" onchange="toggleOptions(this)" <?= $survey['status'] !== 'draft' ? 'disabled' : '' ?>>
                    <option value="text" <?= $q['type']==='text'?'selected':'' ?>>Text</option>
                    <option value="rating_scale" <?= $q['type']==='rating_scale'?'selected':'' ?>>Rating Scale (1–5)</option>
                    <option value="multiple_choice" <?= $q['type']==='multiple_choice'?'selected':'' ?>>Multiple Choice</option>
                    <option value="yes_no" <?= $q['type']==='yes_no'?'selected':'' ?>>Yes/No</option>
                  </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;gap:var(--space-sm);">
                  <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer;margin-bottom:0.6rem;">
                    <input type="checkbox" name="questions[<?= $i ?>][required]" value="1" <?= $q['required']?'checked':'' ?> <?= $survey['status'] !== 'draft' ? 'disabled' : '' ?>>
                    Required
                  </label>
                </div>
              </div>
              <div class="options-container" style="<?= $q['type']==='multiple_choice'?'':'display:none;' ?>">
                <label>Options (one per line)</label>
                <textarea name="questions[<?= $i ?>][options][]" class="form-textarea options-raw" rows="3" placeholder="Option A&#10;Option B&#10;Option C" <?= $survey['status'] !== 'draft' ? 'disabled' : '' ?>><?= is_array($q['options']) ? implode("\n", $q['options']) : '' ?></textarea>
              </div>
            </div>
            <?php if ($survey['status'] === 'draft'): ?>
              <button type="button" onclick="removeQuestion(this)" class="btn btn-ghost btn-sm" style="color:#dc2626;margin-top:1.8rem;" title="Remove">🗑️</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($survey['status'] === 'draft'): ?>
      <div style="display:flex;gap:var(--space-md);margin-top:var(--space-xl);">
        <button type="submit" class="btn btn-primary" onclick="prepareOptions()">💾 Save Questions</button>
        <a href="surveys.php" class="btn btn-ghost">Cancel</a>
      </div>
    <?php endif; ?>
  </form>
</div>

<!-- Question template (hidden) -->
<template id="questionTemplate">
  <div class="question-block" style="border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:var(--space-lg);margin-bottom:var(--space-lg);background:var(--gray-50);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-md);">
      <div style="flex:1;">
        <div class="form-group">
          <label>Question *</label>
          <input type="text" name="" class="form-input" required placeholder="Enter your question...">
        </div>
        <div style="display:flex;gap:var(--space-md);">
          <div class="form-group" style="flex:1;">
            <label>Type</label>
            <select name="" class="form-input q-type" onchange="toggleOptions(this)">
              <option value="text">Text</option>
              <option value="rating_scale">Rating Scale (1–5)</option>
              <option value="multiple_choice">Multiple Choice</option>
              <option value="yes_no">Yes/No</option>
            </select>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;gap:var(--space-sm);">
            <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer;margin-bottom:0.6rem;">
              <input type="checkbox" name="" value="1" checked>
              Required
            </label>
          </div>
        </div>
        <div class="options-container" style="display:none;">
          <label>Options (one per line)</label>
          <textarea name="" class="form-textarea options-raw" rows="3" placeholder="Option A&#10;Option B&#10;Option C"></textarea>
        </div>
      </div>
      <button type="button" onclick="removeQuestion(this)" class="btn btn-ghost btn-sm" style="color:#dc2626;margin-top:1.8rem;" title="Remove">🗑️</button>
    </div>
  </div>
</template>

<script>
let qIndex = <?= count($questions) ?>;

function addQuestion() {
  const tpl = document.getElementById('questionTemplate').content.cloneNode(true);
  const block = tpl.querySelector('.question-block');
  block.dataset.index = qIndex;

  block.querySelector('input[type="text"]').name = `questions[${qIndex}][question]`;
  block.querySelector('select.q-type').name = `questions[${qIndex}][type]`;
  block.querySelector('input[type="checkbox"]').name = `questions[${qIndex}][required]`;
  block.querySelector('textarea.options-raw').name = `questions[${qIndex}][options][]`;

  document.getElementById('questionsContainer').appendChild(tpl);
  qIndex++;
}

function removeQuestion(btn) {
  btn.closest('.question-block').remove();
  reindex();
}

function reindex() {
  document.querySelectorAll('.question-block').forEach((block, i) => {
    block.dataset.index = i;
    block.querySelector('input[type="text"]').name = `questions[${i}][question]`;
    block.querySelector('select.q-type').name = `questions[${i}][type]`;
    block.querySelector('input[type="checkbox"]').name = `questions[${i}][required]`;
    block.querySelector('textarea.options-raw').name = `questions[${i}][options][]`;
  });
  qIndex = document.querySelectorAll('.question-block').length;
}

function toggleOptions(select) {
  const container = select.closest('.question-block').querySelector('.options-container');
  container.style.display = select.value === 'multiple_choice' ? '' : 'none';
}

function prepareOptions() {
  // Convert textarea lines to individual option inputs before submit
  document.querySelectorAll('.question-block').forEach((block, i) => {
    const ta = block.querySelector('textarea.options-raw');
    if (!ta) return;
    const lines = ta.value.split('\n').map(l => l.trim()).filter(Boolean);
    ta.remove();
    lines.forEach(line => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = `questions[${i}][options][]`;
      inp.value = line;
      block.appendChild(inp);
    });
  });
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
