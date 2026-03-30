<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/ScreeningHelper.php';

requireLogin();
requireAnyRole(['staff', 'admin'], 'Staff or Admin access required');

$pdo = getPDO();
$user = $_SESSION['user'] ?? [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk_change_status') {
        $ids = array_filter(array_map('intval', $_POST['application_ids'] ?? []));
        $newStatus = trim($_POST['new_status'] ?? '');
        $allowed = ['submitted','under_review','pending','approved','rejected','waitlisted','draft'];
        if (!empty($ids) && in_array($newStatus, $allowed, true)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE applications SET status = ?, reviewed_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$newStatus], $ids));
            logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'BULK_STATUS_UPDATE', 'applications', null, 'Bulk status changed to: '.$newStatus.' for '.count($ids).' applications');
            $_SESSION['success'] = count($ids) . ' application(s) updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.';
        } else {
            $_SESSION['flash'] = 'No applications selected or invalid status.';
        }
        header('Location: applications.php'); exit;
    }

    if ($action === 'update_status') {
        $appid = (int)($_POST['application_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $allowed = ['submitted','under_review','pending','approved','rejected','waitlisted','draft'];
        if ($appid && in_array($status, $allowed, true)) {
            // Get application details for notification
            $appStmt = $pdo->prepare('SELECT a.*, u.email, u.first_name, u.last_name, s.title as scholarship_title 
                                      FROM applications a 
                                      LEFT JOIN users u ON a.user_id = u.id 
                                      LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                                      WHERE a.id = :id');
            $appStmt->execute([':id' => $appid]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($app) {
                // Update status
                $stmt = $pdo->prepare('UPDATE applications SET status = :status, reviewed_at = NOW() WHERE id = :id');
                $stmt->execute([':status'=>$status, ':id'=>$appid]);
                
                // Create in-app notification
                try {
                    $notifTitle = 'Application Status Updated';
                    $notifMsg = 'Your application for "' . ($app['scholarship_title'] ?? 'scholarship') . '" status has been updated to: ' . ucfirst(str_replace('_', ' ', $status));
                    
                    $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id, created_at) 
                                                VALUES (:uid, :title, :msg, :type, :aid, :sid, NOW())');
                    $notifStmt->execute([
                        ':uid' => $app['user_id'],
                        ':title' => $notifTitle,
                        ':msg' => $notifMsg,
                        ':type' => 'application',
                        ':aid' => $appid,
                        ':sid' => $app['scholarship_id'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log('[Notification Error] ' . $e->getMessage());
                }
                
                // Queue email notification
                require_once __DIR__ . '/../config/email.php';
                
                if (!empty($app['email'])) {
                    $emailSubject = 'Application Status Update - ' . ($app['scholarship_title'] ?? 'Scholarship');
                    $emailBody = '<h2>Application Status Update</h2>';
                    $emailBody .= '<p>Dear ' . htmlspecialchars($app['first_name'] ?? 'Student') . ',</p>';
                    $emailBody .= '<p>Your application for <strong>' . htmlspecialchars($app['scholarship_title'] ?? 'scholarship') . '</strong> has been updated.</p>';
                    $emailBody .= '<p><strong>New Status:</strong> <span style="color: #c41e3a; font-weight: bold;">' . ucfirst(str_replace('_', ' ', $status)) . '</span></p>';
                    $emailBody .= '<p>Please log in to your account to view full details.</p>';
                    $emailBody .= '<p>Best regards,<br>ScholarHub Team</p>';
                    
                    queueEmail($app['email'], $emailSubject, $emailBody, $app['user_id']);
                }
                
                // Log audit trail
                logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'APPLICATION_STATUS_UPDATED', 'applications', $appid, 'Status changed to: ' . $status);
                
                // Auto-create pending disbursement on approval
                if ($status === 'approved') {
                    try {
                        $schStmt = $pdo->prepare('SELECT amount FROM scholarships WHERE id = :id');
                        $schStmt->execute([':id' => $app['scholarship_id']]);
                        $sch = $schStmt->fetch(PDO::FETCH_ASSOC);
                        $disbStmt = $pdo->prepare("INSERT IGNORE INTO disbursements (application_id, user_id, scholarship_id, amount, disbursement_date, status, created_at) VALUES (:app_id, :user_id, :sch_id, :amount, CURDATE(), 'pending', NOW())");
                        $disbStmt->execute([':app_id' => $appid, ':user_id' => $app['user_id'], ':sch_id' => $app['scholarship_id'], ':amount' => $sch['amount'] ?? 0]);
                    } catch (Exception $e) { error_log('[Disbursement] ' . $e->getMessage()); }
                }
                
                $_SESSION['success'] = 'Application status updated to ' . ucfirst(str_replace('_', ' ', $status)) . '. Notification sent to applicant.';
            } else {
                $_SESSION['flash'] = 'Application not found.';
            }
        } else {
            $_SESSION['flash'] = 'Invalid status or application ID.';
        }
    }
    header('Location: applications.php'); exit;
}

// Filters
$q = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

function fraudScoreBadge(int $score): string {
    if ($score <= 0) return '';
    if ($score <= 25) {
        $color = '#16a34a'; $bg = '#dcfce7'; $label = 'Low';
    } elseif ($score <= 50) {
        $color = '#ca8a04'; $bg = '#fef9c3'; $label = 'Med';
    } elseif ($score <= 75) {
        $color = '#ea580c'; $bg = '#ffedd5'; $label = 'High';
    } else {
        $color = '#dc2626'; $bg = '#fee2e2'; $label = 'Critical';
    }
    return '<span title="Fraud Score: ' . $score . '" style="display:inline-block;padding:2px 7px;border-radius:9999px;font-size:0.75rem;font-weight:600;background:' . $bg . ';color:' . $color . ';">' . $score . ' ' . $label . '</span>';
}

$sql = 'SELECT a.id, a.user_id, a.status, a.created_at, COALESCE(a.fraud_score, 0) as fraud_score, s.title as scholarship_title, u.first_name, u.last_name, u.email FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id LEFT JOIN users u ON a.user_id = u.id';
$where = [];
$params = [];
if ($statusFilter) { $where[] = 'a.status = :status'; $params[':status']=$statusFilter; }
if ($q) { $where[] = '(s.title LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q)'; $params[':q']='%'.$q.'%'; }
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

// Count total
$countSql = 'SELECT COUNT(*) FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id LEFT JOIN users u ON a.user_id = u.id';
if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$sql .= ' ORDER BY a.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
$page_title = 'Applications Queue - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>&#128203; Applications Queue</h1>
  <p class="text-muted">Review and manage scholarship applications</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="content-card">
  <div style="display: flex; gap: var(--space-md); margin-bottom: var(--space-xl); flex-wrap: wrap;">
    <form method="GET" style="display: flex; gap: var(--space-md); flex: 1; flex-wrap: wrap;">
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search applicant or scholarship" class="form-input" style="flex: 1; min-width: 200px;">
      <select name="status" class="form-input" style="min-width: 150px;">
        <option value="">All statuses</option>
        <?php 
        $statuses = ['submitted', 'under_review', 'pending', 'approved', 'rejected', 'waitlisted', 'draft'];
        foreach($statuses as $st) {
          $selected = ($statusFilter === $st) ? 'selected' : '';
          echo '<option value="' . $st . '" ' . $selected . '>' . ucfirst(str_replace('_', ' ', $st)) . '</option>';
        }
        ?>
      </select>
      <button type="submit" class="btn btn-primary">&#128269; Filter</button>
    </form>
  </div>

  <?php if (!empty($apps)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Applicant</th>
          <th>Scholarship</th>
          <th>Status</th>
          <th>Fraud</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($apps as $a): ?>
          <tr>
            <td><?= (int)$a['id'] ?></td>
            <td>
              <strong><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($a['email'] ?? '') ?></small>
            </td>
            <td><?= htmlspecialchars($a['scholarship_title'] ?? 'N/A') ?></td>
            <td>
              <span class="status-badge status-<?= strtolower($a['status']) ?>">
                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a['status']))) ?>
              </span>
            </td>
            <td><?= fraudScoreBadge((int)($a['fraud_score'] ?? 0)) ?></td>
            <td><small><?= date('M d, Y', strtotime($a['created_at'])) ?></small></td>
            <td>
              <a href="application_view.php?id=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">&#128065; View</a>
              <form method="POST" style="display: inline-block; margin-left: var(--space-xs);">
                <select name="status" class="form-input" style="display: inline-block; width: auto; padding: 4px 8px; font-size: 0.875rem;">
                  <?php foreach($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= $a['status'] === $st ? 'selected' : '' ?>>
                      <?= ucfirst(str_replace('_', ' ', $st)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="application_id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">&#10003; Update</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">&#128203;</div>
      <h3 class="empty-state-title">No Applications Found</h3>
      <p class="empty-state-description">No applications match your search criteria.</p>
    </div>
  <?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:var(--space-md);margin-top:var(--space-xl);padding-top:var(--space-lg);border-top:1px solid var(--gray-200);">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-ghost">← Previous</a>
      <?php endif; ?>
      <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalCount ?> total)</span>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-ghost">Next →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
