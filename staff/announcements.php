<?php
/**
 * STAFF — ANNOUNCEMENTS
 * Role: Staff / Admin
 * Purpose: View and manage system-wide announcements
 * URL: /staff/announcements.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireAnyRole(['admin', 'staff'], 'Staff access required');

$pdo = getPDO();
$userRole = $_SESSION['user']['role'] ?? 'staff';

$message = $_SESSION['flash'] ?? $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message'], $_SESSION['message_type'], $_SESSION['flash']);

// Handle form submission (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extra server-side guard: ensure only admin can perform announcement actions
    if (empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['message'] = 'Permission denied. Only admins can create/edit announcements.';
        $_SESSION['message_type'] = 'error';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = 'Invalid request (CSRF token missing or incorrect).';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitizeString($_POST['title'] ?? '');
        $message_content = sanitizeString($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'info';
        $expires_at = $_POST['expires_at'] ?? null;
        
        if ($title && $message_content) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (title, message, type, created_by, published, published_at, expires_at)
                    VALUES (:title, :message, :type, :created_by, 1, NOW(), :expires_at)
                ");
                    $stmt->execute([
                        ':title' => $title,
                        ':message' => $message_content,
                        ':type' => $type,
                        ':created_by' => $_SESSION['user_id'],
                        ':expires_at' => !empty($expires_at) ? $expires_at : null
                    ]);
                    $newId = $pdo->lastInsertId();
                    // Audit removed
                    $_SESSION['message'] = 'Announcement created successfully!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'unpublish') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        if ($id) {
            try {
                        // capture previous row for audit
                        $old = $pdo->prepare("SELECT * FROM announcements WHERE id = :id");
                        $old->execute([':id' => $id]);
                        $oldRow = $old->fetch(PDO::FETCH_ASSOC);

                        $pdo->prepare("UPDATE announcements SET published = 0 WHERE id = :id")
                            ->execute([':id' => $id]);

                        // Audit removed
                        $_SESSION['message'] = 'Announcement unpublished!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        if ($id) {
            try {
                        // capture previous row for audit
                        $old = $pdo->prepare("SELECT * FROM announcements WHERE id = :id");
                        $old->execute([':id' => $id]);
                        $oldRow = $old->fetch(PDO::FETCH_ASSOC);

                        $pdo->prepare("DELETE FROM announcements WHERE id = :id")
                            ->execute([':id' => $id]);

                        // Audit removed
                        $_SESSION['message'] = 'Announcement deleted!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch announcements
$announcements = $pdo->query("
    SELECT a.*, u.first_name, u.last_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.published_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<?php
$page_title = 'Announcements - Admin';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📢 Announcements</h1>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= $message_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
  <h2 style="margin:0;">All Announcements</h2>
  <?php if ($userRole === 'admin'): ?>
    <button type="button" class="btn btn-primary btn-sm" id="toggleFormBtn" onclick="toggleForm()">+ New Announcement</button>
  <?php endif; ?>
</div>

<?php if ($userRole === 'admin'): ?>
<div class="content-card" style="margin-bottom:1.5rem;display:none;" id="formCard">
  <h3 style="margin-bottom:1rem;">Create New Announcement</h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Title *</label>
        <input type="text" name="title" class="form-input" required placeholder="e.g., Scholarship Deadline Extended">
      </div>
      <div class="form-group">
        <label class="form-label">Type</label>
        <select name="type" class="form-input">
          <option value="info">Info</option>
          <option value="success">Success</option>
          <option value="warning">Warning</option>
          <option value="urgent">Urgent</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Message *</label>
      <textarea name="message" class="form-input" rows="4" required placeholder="Enter the announcement message..."></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Expires At (Optional)</label>
      <input type="datetime-local" name="expires_at" class="form-input">
    </div>
    <button type="submit" class="btn btn-primary">Post Announcement</button>
  </form>
</div>

<script>
function toggleForm() {
  const formCard = document.getElementById('formCard');
  const btn = document.getElementById('toggleFormBtn');
  if (formCard.style.display === 'none') {
    formCard.style.display = 'block';
    btn.textContent = '✕ Close';
  } else {
    formCard.style.display = 'none';
    btn.textContent = '+ New Announcement';
  }
}
</script>
<?php endif; ?>

<div class="content-card">
  <h3 style="margin-bottom:1rem;">All Announcements</h3>
  <?php if (!empty($announcements)): ?>
    <?php foreach ($announcements as $ann): ?>
      <div style="padding:1rem;border-radius:10px;border:1.5px solid #D1D5DB;margin-bottom:0.75rem;position:relative;background:<?= !$ann['published'] ? '#fafafa' : '#fff' ?>;">
        <?php if (!$ann['published']): ?>
          <span style="position:absolute;top:0.75rem;right:0.75rem;background:#9E9E9E;color:#fff;padding:2px 8px;border-radius:999px;font-size:0.7rem;font-weight:600;">UNPUBLISHED</span>
        <?php endif; ?>
        <div style="font-weight:700;font-size:0.9375rem;color:#1a1a2e;margin-bottom:0.25rem;"><?= htmlspecialchars($ann['title']) ?></div>
        <div style="font-size:0.8rem;color:#9E9E9E;margin-bottom:0.5rem;">
          Posted by <?= htmlspecialchars($ann['first_name'] . ' ' . $ann['last_name']) ?> on <?= date('M d, Y H:i', strtotime($ann['published_at'])) ?>
          <?php if ($ann['expires_at']): ?> &bull; Expires: <?= date('M d, Y', strtotime($ann['expires_at'])) ?><?php endif; ?>
        </div>
        <div style="font-size:0.875rem;color:#424242;margin-bottom:0.75rem;"><?= nl2br(htmlspecialchars($ann['message'])) ?></div>
        <?php if ($userRole === 'admin'): ?>
        <div style="display:flex;gap:0.5rem;">
          <?php if ($ann['published']): ?>
            <form style="display:inline;" method="POST" onsubmit="return confirm('Unpublish this announcement?');">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
              <input type="hidden" name="action" value="unpublish">
              <input type="hidden" name="id" value="<?= $ann['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Unpublish</button>
            </form>
          <?php endif; ?>
          <form style="display:inline;" method="POST" onsubmit="return confirm('Delete this announcement?');">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $ann['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">??</div>
      <div class="empty-state-title">No Announcements</div>
      <div class="empty-state-description">Create your first announcement to get started.</div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
