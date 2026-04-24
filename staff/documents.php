<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');
$pdo = getPDO();
$csrf_token = generateCSRFToken();

// Fetch all documents with application info
$stmt = $pdo->query("SELECT d.id, d.file_path, d.document_type, d.uploaded_at, d.application_id,
                     u.first_name, u.last_name, u.email, a.id as application_id, s.title as scholarship_title
                     FROM documents d
                     LEFT JOIN applications a ON d.application_id = a.id
                     LEFT JOIN users u ON a.user_id = u.id
                     LEFT JOIN scholarships s ON a.scholarship_id = s.id
                     ORDER BY d.uploaded_at DESC");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<?php
$page_title = 'Documents Queue - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-file"></i> Application Documents</h1>
  <p class="text-muted">View all submitted documents from applicants</p>
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
      <div class="empty-state-icon"><i class="fas fa-file"></i></div>
      <h3 class="empty-state-title">No Documents</h3>
      <p class="empty-state-description">No documents have been submitted yet.</p>
    </div>
  <?php else: ?>
      <table class="modern-table">
        <thead><tr><th>#</th><th>File</th><th>Applicant</th><th>Scholarship</th><th>Uploaded</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($docs as $i => $d): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><a href="../<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="text-primary"><?= htmlspecialchars(basename($d['file_path'])) ?></a><br><small class="text-muted"><?= htmlspecialchars($d['document_type']) ?></small></td>
              <td><?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?></td>
              <td><?= htmlspecialchars($d['scholarship_title'] ?? '—') ?></td>
              <td><small><?= htmlspecialchars($d['uploaded_at']) ?></small></td>
              <td>
                <?php if ($d['application_id']): ?>
                  <a href="application_view.php?id=<?= (int)$d['application_id'] ?>" class="btn btn-ghost btn-sm">View Application</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
