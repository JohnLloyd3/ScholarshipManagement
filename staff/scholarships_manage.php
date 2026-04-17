<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

$pdo = getPDO();

// Handle actions: publish/unpublish/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: scholarships_manage.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM scholarships WHERE id = :id')->execute([':id'=>$id]);
        $_SESSION['success'] = 'Scholarship deleted.';
    } elseif (($action === 'publish' || $action === 'unpublish') && $id) {
        $status = $action === 'publish' ? 'open' : 'closed';
        $pdo->prepare('UPDATE scholarships SET status = :status WHERE id = :id')->execute([':status'=>$status, ':id'=>$id]);
        $_SESSION['success'] = 'Scholarship updated.';
    }
    header('Location: scholarships_manage.php'); exit;
}

$stmt = $pdo->query('SELECT * FROM scholarships ORDER BY created_at DESC');
$schs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$page_title = 'Manage Scholarships - ScholarHub';
$base_path = '../';
$csrf_token = generateCSRFToken();
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🎓 Manage Scholarships</h1>
  <p class="text-muted">Create, edit, and manage scholarship programs</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="content-card">
  <div style="margin-bottom:var(--space-xl)">
    <a class="btn btn-primary" href="scholarship_form.php">➕ Create New Scholarship</a>
  </div>
  
  <table class="modern-table">
    <thead><tr><th>#</th><th>Title</th><th>Organization</th><th>Deadline</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($schs as $s): ?>
        <tr>
          <td><?= (int)$s['id'] ?></td>
          <td><?= htmlspecialchars($s['title']) ?></td>
          <td><?= htmlspecialchars($s['organization'] ?? '') ?></td>
          <td><small><?= htmlspecialchars($s['deadline'] ?? '—') ?></small></td>
          <td><span class="status-badge status-<?= strtolower($s['status'] ?? 'pending') ?>"><?= htmlspecialchars(ucfirst($s['status'] ?? '')) ?></span></td>
          <td>
            <a class="btn btn-ghost btn-sm" href="scholarship_form.php?id=<?= (int)$s['id'] ?>">✏️ Edit</a>
            <form method="post" style="display:inline-block;margin-left:var(--space-sm)">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <?php if (($s['status'] ?? '')==='open'): ?>
                <button class="btn btn-secondary btn-sm" name="action" value="unpublish">📴 Unpublish</button>
              <?php else: ?>
                <button class="btn btn-primary btn-sm" name="action" value="publish">📢 Publish</button>
              <?php endif; ?>
            </form>
            <form method="post" style="display:inline-block;margin-left:var(--space-sm)" onsubmit="return confirm('Delete this scholarship?');">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
              <button class="btn btn-ghost btn-sm" name="action" value="delete" style="color:var(--red-primary)">🗑️ Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
