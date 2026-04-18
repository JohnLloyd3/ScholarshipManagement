<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');
$pdo = getPDO();
$csrf_token = generateCSRFToken();

// Fetch pending/submitted/under_review applications
$stmt = $pdo->query("SELECT a.id, a.title, a.status, a.created_at, u.username, u.first_name, u.last_name, s.title as scholarship_title
                     FROM applications a
                     LEFT JOIN users u ON a.user_id = u.id
                     LEFT JOIN scholarships s ON a.scholarship_id = s.id
                     WHERE a.status IN ('submitted','pending','under_review')
                     ORDER BY a.created_at ASC");
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<?php
$page_title = 'Pending Applications - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>⏳ Pending Applications</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
  <?php if (empty($apps)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">✅</div>
      <h3 class="empty-state-title">No Pending Applications</h3>
      <p class="empty-state-description">All applications have been reviewed!</p>
    </div>
  <?php else: ?>
    <form method="POST" action="applications.php">
      <input type="hidden" name="action" value="bulk_change_status">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <table class="modern-table">
        <thead><tr><th><input type="checkbox" id="select_all" onclick="toggleAll(this)"></th><th>#</th><th>Applicant</th><th>Title</th><th>Scholarship</th><th>Status</th><th>Submitted</th></tr></thead>
        <tbody>
          <?php foreach ($apps as $a): ?>
            <tr>
              <td><input type="checkbox" name="application_ids[]" value="<?= (int)$a['id'] ?>"></td>
              <td><?= htmlspecialchars($a['id']) ?></td>
              <td><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '') ?: ($a['username'] ?? '')) ?></td>
              <td><?= htmlspecialchars($a['title'] ?? 'Application') ?></td>
              <td><?= htmlspecialchars($a['scholarship_title'] ?? '—') ?></td>
              <td><span class="status-badge status-<?= strtolower($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
              <td><small><?= htmlspecialchars($a['created_at']) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:var(--space-xl);display:flex;gap:var(--space-md);align-items:center;flex-wrap:wrap">
        <select name="new_status" class="form-select" style="width:auto">
          <option value="under_review">Mark as Under Review</option>
          <option value="approved">Mark as Approved</option>
          <option value="rejected">Mark as Rejected</option>
          <option value="pending">Mark as Pending</option>
        </select>
        <input type="text" name="notes" placeholder="Optional notes for applicants" class="form-input" style="flex:1;min-width:300px">
        <button class="btn btn-primary" type="submit">✅ Apply to Selected</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
  function toggleAll(cb){
    document.querySelectorAll('input[name="application_ids[]"]').forEach(function(i){ i.checked = cb.checked; });
  }
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
