<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$csrf_token = generateCSRFToken();

// Handle retry action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: email_queue.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    $emailId = (int)($_POST['email_id'] ?? 0);

    if ($action === 'retry' && $emailId) {
        try {
            require_once __DIR__ . '/../config/email.php';
            $stmt = $pdo->prepare('SELECT * FROM email_logs WHERE id = :id');
            $stmt->execute([':id' => $emailId]);
            $email = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($email) {
                $sent = sendEmail($email['email'], $email['subject'], $email['body'], true);
                $pdo->prepare('UPDATE email_logs SET status = :s, attempts = attempts + 1, last_attempt_at = NOW() WHERE id = :id')
                    ->execute([':s' => $sent ? 'sent' : 'failed', ':id' => $emailId]);
                $_SESSION['success'] = $sent ? 'Email resent successfully.' : 'Retry failed. Check SMTP settings.';
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = 'Error: ' . $e->getMessage();
        }
    }

    if ($action === 'delete' && $emailId) {
        try {
            $pdo->prepare('DELETE FROM email_logs WHERE id = :id')->execute([':id' => $emailId]);
            $_SESSION['success'] = 'Email log entry deleted.';
        } catch (Exception $e) {
            $_SESSION['flash'] = 'Error: ' . $e->getMessage();
        }
    }

    header('Location: email_queue.php');
    exit;
}

// Fetch email logs
$filterStatus = $_GET['status'] ?? '';
$emails = [];
try {
    $pdo->query('SELECT 1 FROM email_logs LIMIT 1');
    $where = $filterStatus ? 'WHERE status = :status' : '';
    $params = $filterStatus ? [':status' => $filterStatus] : [];
    $stmt = $pdo->prepare("SELECT el.*, u.username FROM email_logs el LEFT JOIN users u ON el.user_id = u.id $where ORDER BY el.created_at DESC LIMIT 200");
    $stmt->execute($params);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $emails = [];
}

$byStatus = array_count_values(array_column($emails, 'status'));

$page_title = 'Email Queue - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📧 Email Queue</h1>
  <p class="text-muted">View all outgoing emails and their delivery status</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-value"><?= count($emails) ?></div>
    <div class="stat-label">Total Shown</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $byStatus['sent'] ?? 0 ?></div>
    <div class="stat-label">Sent</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $byStatus['queued'] ?? 0 ?></div>
    <div class="stat-label">Queued</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $byStatus['failed'] ?? 0 ?></div>
    <div class="stat-label">Failed</div>
  </div>
</div>

<!-- Filter -->
<div class="content-card" style="margin-bottom:var(--space-lg);">
  <form method="GET" style="display:flex;gap:var(--space-md);align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Status</label>
      <select name="status" class="form-input">
        <option value="">All</option>
        <?php foreach (['queued','sent','failed'] as $s): ?>
          <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="email_queue.php" class="btn btn-ghost">Clear</a>
  </form>
</div>

<div class="content-card">
  <h2>📬 Email Log</h2>
  <?php if (!empty($emails)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead>
        <tr><th>To</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Sent At</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($emails as $e): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($e['email']) ?></strong>
              <?php if ($e['username']): ?><br><small class="text-muted"><?= htmlspecialchars($e['username']) ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars(mb_strimwidth($e['subject'], 0, 60, '…')) ?></td>
            <td><span class="status-badge status-<?= $e['status'] === 'sent' ? 'approved' : ($e['status'] === 'failed' ? 'rejected' : 'submitted') ?>"><?= ucfirst($e['status']) ?></span></td>
            <td><?= (int)$e['attempts'] ?></td>
            <td><small><?= $e['last_attempt_at'] ? date('M d, Y H:i', strtotime($e['last_attempt_at'])) : '—' ?></small></td>
            <td>
              <?php if ($e['status'] !== 'sent'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="retry">
                  <input type="hidden" name="email_id" value="<?= (int)$e['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">🔄 Retry</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this log entry?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="email_id" value="<?= (int)$e['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626;">🗑️</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon">📧</div>
      <h3 class="empty-state-title">No Emails Found</h3>
      <p class="empty-state-description">Email logs will appear here once emails are sent.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
