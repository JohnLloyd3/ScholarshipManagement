<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/ScreeningHelper.php';
requireLogin();
requireRole('admin', 'Admin access required');
$pdo = getPDO();

// Handle restore or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: scholarship_archive.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'restore' && $id > 0) {
        $pdo->prepare("UPDATE scholarships SET status = 'open' WHERE id = :id")->execute([':id' => $id]);
        if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'SCHOLARSHIP_RESTORED', 'scholarships', $id, 'Restored from archive');
        $_SESSION['success'] = 'Scholarship restored.';
    } elseif ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM scholarships WHERE id = :id')->execute([':id' => $id]);
        if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'SCHOLARSHIP_DELETED', 'scholarships', $id, 'Deleted from archive');
        $_SESSION['success'] = 'Scholarship deleted permanently.';
    }
    header('Location: scholarship_archive.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM scholarships WHERE status = 'archived' ORDER BY updated_at DESC");
$stmt->execute();
$archived = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
$page_title = 'Scholarship Archive - Admin';
$base_path = '../';
$csrf_token = generateCSRFToken();
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🗄️ Scholarship Archive</h1>
  <p class="text-muted">Past and archived scholarships</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="content-card">
  <?php if (empty($archived)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">🗄️</div>
      <h3 class="empty-state-title">No Archived Scholarships</h3>
      <p class="empty-state-description">Archived scholarships will appear here.</p>
    </div>
  <?php else: ?>
    <table class="modern-table">
      <thead>
        <tr><th>Title</th><th>Organization</th><th>Deadline</th><th>Updated</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($archived as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['title']) ?></td>
            <td><?= htmlspecialchars($a['organization'] ?? '') ?></td>
            <td><?= htmlspecialchars($a['deadline'] ?? '') ?></td>
            <td><small><?= htmlspecialchars($a['updated_at'] ?? $a['created_at'] ?? '') ?></small></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button name="action" value="restore" class="btn btn-primary btn-sm">Restore</button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete permanently?');">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button name="action" value="delete" class="btn btn-ghost btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
