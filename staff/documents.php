<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_role(['staff','admin']);
$pdo = getPDO();

// Fetch pending documents
$stmt = $pdo->query("SELECT d.id, d.file_name, d.file_path, d.document_type, d.verification_status, d.uploaded_at, u.username, a.id as application_id, s.title as scholarship_title
                     FROM documents d
                     LEFT JOIN users u ON d.user_id = u.id
                     LEFT JOIN applications a ON d.application_id = a.id
                     LEFT JOIN scholarships s ON a.scholarship_id = s.id
                     WHERE d.verification_status = 'pending'
                     ORDER BY d.uploaded_at ASC");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<?php
$page_title = 'Documents Queue - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📄 Pending Documents</h1>
  <p class="text-muted">Verify or reject submitted documents in bulk</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
  <?php if (empty($docs)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📄</div>
      <h3 class="empty-state-title">No Pending Documents</h3>
      <p class="empty-state-description">All documents have been reviewed!</p>
    </div>
  <?php else: ?>
    <form method="POST" action="../controllers/DocumentController.php">
      <input type="hidden" name="action" value="verify_documents_bulk">
      <table class="modern-table">
        <thead><tr><th><input type="checkbox" id="select_all" onclick="toggleAll(this)"></th><th>File</th><th>Applicant</th><th>Scholarship</th><th>Uploaded</th></tr></thead>
        <tbody>
          <?php foreach ($docs as $d): ?>
            <tr>
              <td><input type="checkbox" name="document_ids[]" value="<?= (int)$d['id'] ?>"></td>
              <td><a href="../<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="text-primary"><?= htmlspecialchars($d['file_name']) ?></a><br><small class="text-muted"><?= htmlspecialchars($d['document_type']) ?></small></td>
              <td><?= htmlspecialchars($d['username']) ?></td>
              <td><?= htmlspecialchars($d['scholarship_title'] ?? '—') ?></td>
              <td><small><?= htmlspecialchars($d['uploaded_at']) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:var(--space-xl);display:flex;gap:var(--space-md);align-items:center;flex-wrap:wrap">
        <select name="new_status" class="form-select" style="width:auto">
          <option value="verified">Mark as Verified</option>
          <option value="rejected">Mark as Rejected</option>
          <option value="needs_resubmission">Mark as Needs Resubmission</option>
        </select>
        <input type="text" name="notes" placeholder="Optional note for applicants" class="form-input" style="flex:1;min-width:300px">
        <button class="btn btn-primary" type="submit">✅ Apply to Selected</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
  function toggleAll(cb){
    document.querySelectorAll('input[name="document_ids[]"]').forEach(function(i){ i.checked = cb.checked; });
  }
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
