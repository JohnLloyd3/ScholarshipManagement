<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extra server-side guard: ensure only admin can perform announcement actions
    if (empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        $_SESSION['message'] = 'Permission denied. Admins only.';
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
                    // Audit log: announcement created
                    try {
                        $auditStmt = $pdo->prepare(
                            "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                             VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)"
                        );
                        $newValues = json_encode(['title' => $title, 'message' => $message_content, 'type' => $type, 'published' => 1, 'expires_at' => $expires_at]);
                        $auditStmt->execute([
                            ':user_id' => $_SESSION['user_id'],
                            ':action' => 'ANNOUNCEMENT_CREATED',
                            ':entity_type' => 'announcements',
                            ':entity_id' => $newId,
                            ':old_values' => null,
                            ':new_values' => $newValues,
                            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                    } catch (Exception $e) {
                        // non-fatal: continue
                    }
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

                        // audit log: announcement unpublished
                        try {
                            $auditStmt = $pdo->prepare(
                                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                                 VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)"
                            );
                            $oldValues = $oldRow ? json_encode($oldRow) : null;
                            $newValues = json_encode(['published' => 0]);
                            $auditStmt->execute([
                                ':user_id' => $_SESSION['user_id'],
                                ':action' => 'ANNOUNCEMENT_UNPUBLISHED',
                                ':entity_type' => 'announcements',
                                ':entity_id' => $id,
                                ':old_values' => $oldValues,
                                ':new_values' => $newValues,
                                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                        } catch (Exception $e) {
                            // continue
                        }
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

                        // audit log: announcement deleted
                        try {
                            $auditStmt = $pdo->prepare(
                                "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                                 VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)"
                            );
                            $oldValues = $oldRow ? json_encode($oldRow) : null;
                            $auditStmt->execute([
                                ':user_id' => $_SESSION['user_id'],
                                ':action' => 'ANNOUNCEMENT_DELETED',
                                ':entity_type' => 'announcements',
                                ':entity_id' => $id,
                                ':old_values' => $oldValues,
                                ':new_values' => null,
                                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                        } catch (Exception $e) {
                            // continue
                        }
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
  <p class="text-muted">Create and manage system announcements</p>
</div>

<?php if ($message): ?>
  <div class="alert alert-success"><?= sanitizeString($message) ?></div>
<?php endif; ?>

<div class="content-card" style="margin-bottom: var(--space-xl);">
  <h3 style="margin-bottom: var(--space-xl);">Create New Announcement</h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input type="hidden" name="action" value="create">
    
    <div class="grid-2" style="margin-bottom: var(--space-lg);">
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

    <button type="submit" class="btn btn-primary">📢 Post Announcement</button>
  </form>
</div>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-xl);">All Announcements</h3>
  
  <?php if (!empty($announcements)): ?>
    <?php foreach ($announcements as $ann): ?>
      <div class="alert alert-<?= $ann['type'] ?>" style="margin-bottom: var(--space-md); position: relative;">
        <?php if (!$ann['published']): ?>
          <span style="position: absolute; top: var(--space-sm); right: var(--space-sm); background: var(--gray-500); color: white; padding: 2px 8px; border-radius: var(--radius-sm); font-size: 11px;">UNPUBLISHED</span>
        <?php endif; ?>
        <h4 style="margin-bottom: var(--space-xs);"><?= sanitizeString($ann['title']) ?></h4>
        <p class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-sm);">
          Posted by <?= sanitizeString($ann['first_name'] . ' ' . $ann['last_name']) ?> on <?= date('M d, Y H:i', strtotime($ann['published_at'])) ?>
          <?php if ($ann['expires_at']): ?>
            | Expires: <?= date('M d, Y', strtotime($ann['expires_at'])) ?>
          <?php endif; ?>
        </p>
        <p style="margin-bottom: var(--space-md);"><?= nl2br(sanitizeString($ann['message'])) ?></p>
        <div style="display: flex; gap: var(--space-sm);">
          <?php if ($ann['published']): ?>
            <form style="display: inline;" method="POST" onsubmit="return confirm('Unpublish this announcement?');">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
              <input type="hidden" name="action" value="unpublish">
              <input type="hidden" name="id" value="<?= $ann['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">Unpublish</button>
            </form>
          <?php endif; ?>
          <form style="display: inline;" method="POST" onsubmit="return confirm('Delete this announcement?');">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $ann['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📢</div>
      <h3 class="empty-state-title">No Announcements</h3>
      <p class="empty-state-description">Create your first announcement to get started.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
