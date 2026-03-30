<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/SurveyHelper.php';

requireLogin();
requireAnyRole(['admin'], 'Admin access required');

$pdo        = getPDO();
$userId     = $_SESSION['user_id'];
$surveys    = getAllSurveys($pdo);
$scholarships = $pdo->query("SELECT id, title FROM scholarships ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$csrf_token = generateCSRFToken();

$page_title = 'Surveys - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Surveys</h1>
  <p class="text-muted">Create and manage feedback surveys</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);">
    <h2>All Surveys</h2>
    <button onclick="document.getElementById('createModal').style.display='block'" class="btn btn-primary">➕ New Survey</button>
  </div>

  <?php if (!empty($surveys)): ?>
    <table class="modern-table">
      <thead><tr><th>Title</th><th>Scholarship</th><th>Cycle</th><th>Status</th><th>Responses</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($surveys as $s): ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
            <td><?= htmlspecialchars($s['scholarship_title'] ?? 'All') ?></td>
            <td><?= htmlspecialchars($s['cycle_label'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
            <td><?= (int)$s['response_count'] ?></td>
            <td>
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                <a href="survey_builder.php?id=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm" title="Builder">🔧</a>
                <a href="survey_results.php?id=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm" title="Results">📊</a>
                <?php if($s['status'] === 'draft'): ?>
                  <form method="POST" action="../controllers/SurveyController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="survey_id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:#16a34a;" title="Activate">▶️</button>
                  </form>
                  <button onclick="openEditModal(<?= htmlspecialchars(json_encode($s)) ?>)" class="btn btn-ghost btn-sm" title="Edit">✏️</button>
                  <form method="POST" action="../controllers/SurveyController.php" style="display:inline;" onsubmit="return confirm('Delete this survey?')">
                    <input type="hidden" name="action" value="delete_survey">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="survey_id" value="<?= (int)$s['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626;" title="Delete">🗑️</button>
                  </form>
                <?php elseif($s['status'] === 'active'): ?>
                  <form method="POST" action="../controllers/SurveyController.php" style="display:inline;" onsubmit="return confirm('Close this survey?')">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="survey_id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="status" value="closed">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626;" title="Close">⏹️</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3 class="empty-state-title">No Surveys</h3>
      <p class="empty-state-description">Create your first survey.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal">
  <div class="modal-content" style="max-width:520px;">
    <div class="modal-header">
      <h2>➕ New Survey</h2>
      <span class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" action="../controllers/SurveyController.php">
      <input type="hidden" name="action" value="create_survey">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="form-group"><label>Title *</label><input type="text" name="title" class="form-input" required></div>
      <div class="form-group"><label>Description</label><textarea name="description" class="form-textarea" rows="2"></textarea></div>
      <div class="form-group">
        <label>Scholarship (leave blank for all)</label>
        <select name="scholarship_id" class="form-input">
          <option value="">All Scholarships</option>
          <?php foreach($scholarships as $sch): ?>
            <option value="<?= (int)$sch['id'] ?>"><?= htmlspecialchars($sch['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Cycle Label</label><input type="text" name="cycle_label" class="form-input" placeholder="e.g. AY 2025-2026 Sem 1"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Create & Build</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content" style="max-width:520px;">
    <div class="modal-header">
      <h2>✏️ Edit Survey</h2>
      <span class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" action="../controllers/SurveyController.php">
      <input type="hidden" name="action" value="update_survey">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="survey_id" id="edit_survey_id">
      <div class="form-group"><label>Title *</label><input type="text" name="title" id="edit_title" class="form-input" required></div>
      <div class="form-group"><label>Description</label><textarea name="description" id="edit_desc" class="form-textarea" rows="2"></textarea></div>
      <div class="form-group">
        <label>Scholarship</label>
        <select name="scholarship_id" id="edit_sch" class="form-input">
          <option value="">All Scholarships</option>
          <?php foreach($scholarships as $sch): ?>
            <option value="<?= (int)$sch['id'] ?>"><?= htmlspecialchars($sch['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Cycle Label</label><input type="text" name="cycle_label" id="edit_cycle" class="form-input"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(s) {
  document.getElementById('edit_survey_id').value = s.id;
  document.getElementById('edit_title').value = s.title;
  document.getElementById('edit_desc').value = s.description || '';
  document.getElementById('edit_sch').value = s.scholarship_id || '';
  document.getElementById('edit_cycle').value = s.cycle_label || '';
  document.getElementById('editModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
