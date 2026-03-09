<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Admin only
if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    $_SESSION['flash'] = 'Admin access only.';
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getPDO();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request.';
        header('Location: email_queue.php'); exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'retry' && $id > 0) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM email_logs WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                $sent = sendEmail($row['email'], $row['subject'], $row['body'], true);
                $status = $sent ? 'sent' : 'failed';
                $upd = $pdo->prepare('UPDATE email_logs SET status = :status, attempts = attempts + 1, last_attempt_at = NOW() WHERE id = :id');
                $upd->execute([':status' => $status, ':id' => $id]);
                $_SESSION['success'] = $sent ? 'Email sent.' : 'Retry failed.';
            } else {
                $_SESSION['flash'] = 'Email not found.';
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = 'Error: ' . $e->getMessage();
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $pdo->prepare('DELETE FROM email_logs WHERE id = :id')->execute([':id' => $id]);
            $_SESSION['success'] = 'Email log deleted.';
        } catch (Exception $e) {
            $_SESSION['flash'] = 'Failed to delete.';
        }
    } elseif ($action === 'retry_all') {
        try {
            $stmt = $pdo->query("SELECT id FROM email_logs WHERE status IN ('queued','failed') ORDER BY created_at ASC LIMIT 50");
            $ids = $stmt->fetchAll();
            $count = 0;
            foreach ($ids as $r) {
                $sid = (int)$r['id'];
                $s = $pdo->prepare('SELECT * FROM email_logs WHERE id = :id');
                $s->execute([':id' => $sid]);
                $row = $s->fetch();
                if (!$row) continue;
                $sent = sendEmail($row['email'], $row['subject'], $row['body'], true);
                $status = $sent ? 'sent' : 'failed';
                $upd = $pdo->prepare('UPDATE email_logs SET status = :status, attempts = attempts + 1, last_attempt_at = NOW() WHERE id = :id');
                $upd->execute([':status' => $status, ':id' => $sid]);
                if ($sent) $count++;
            }
            $_SESSION['success'] = "Retried emails; successful: $count";
        } catch (Exception $e) {
            $_SESSION['flash'] = 'Bulk retry failed.';
        }
    }

    header('Location: email_queue.php'); exit;
}

$logs = $pdo->query('SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 200')->fetchAll();

// Get statistics
$stats = [];
$stats['total'] = $pdo->query('SELECT COUNT(*) FROM email_logs')->fetchColumn();
$stats['queued'] = $pdo->query('SELECT COUNT(*) FROM email_logs WHERE status = "queued"')->fetchColumn();
$stats['sent'] = $pdo->query('SELECT COUNT(*) FROM email_logs WHERE status = "sent"')->fetchColumn();
$stats['failed'] = $pdo->query('SELECT COUNT(*) FROM email_logs WHERE status = "failed"')->fetchColumn();
$stats['today'] = $pdo->query('SELECT COUNT(*) FROM email_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
?>
<?php
$page_title = 'Email Queue - Admin';
$base_path = '../';
$extra_css = '
  .stats-mini { display: grid; grid-template-columns: repeat(5, 1fr); gap: var(--space-md); margin-bottom: var(--space-xl); }
  .stat-mini { background: var(--white); padding: var(--space-md); border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow-sm); }
  .stat-mini-value { font-size: 1.5rem; font-weight: 700; color: var(--red-primary); }
  .stat-mini-label { color: var(--gray-600); margin-top: var(--space-xs); font-size: 0.75rem; }
';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📧 Email Queue</h1>
  <p class="text-muted">Manage email delivery and logs</p>
</div>

<div class="stats-mini">
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['total']) ?></div>
    <div class="stat-mini-label">Total Emails</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['queued']) ?></div>
    <div class="stat-mini-label">Queued</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['sent']) ?></div>
    <div class="stat-mini-label">Sent</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['failed']) ?></div>
    <div class="stat-mini-label">Failed</div>
  </div>
  <div class="stat-mini">
    <div class="stat-mini-value"><?= number_format($stats['today']) ?></div>
    <div class="stat-mini-label">Today</div>
  </div>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card" style="margin-bottom: var(--space-xl);">
  <form method="POST" style="display:flex;gap:var(--space-md);align-items:center;">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input type="hidden" name="action" value="retry_all">
    <button class="btn btn-primary" type="submit">Retry Recent Queued/Failed (max 50)</button>
  </form>
</div>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-lg);">Email Logs</h3>
  <table class="modern-table">
    <thead>
      <tr><th>ID</th><th>To</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Last Attempt</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $row): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><?= htmlspecialchars($row['subject']) ?></td>
          <td><span class="status-badge status-<?= $row['status'] ?>"><?= htmlspecialchars($row['status']) ?></span></td>
          <td><?= (int)$row['attempts'] ?></td>
          <td><small><?= htmlspecialchars($row['last_attempt_at']) ?></small></td>
          <td><small><?= htmlspecialchars($row['created_at']) ?></small></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="action" value="retry">
              <button class="btn btn-ghost btn-sm" type="submit">Retry</button>
            </form>
            <form method="POST" style="display:inline;margin-left:6px">
              <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Delete this log?')">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
