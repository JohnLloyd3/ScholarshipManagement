<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

requireLogin();
requireRole('staff', 'Staff access required');

$pdo = getPDO();
$user = $_SESSION['user'] ?? [];

// Handle POST actions: update_status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
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
                if (function_exists('logAuditTrail')) {
                    require_once __DIR__ . '/../helpers/ScreeningHelper.php';
                    logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'APPLICATION_STATUS_UPDATED', 'applications', $appid, 'Status changed to: ' . $status);
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

$sql = 'SELECT a.id, a.user_id, a.status, a.created_at, s.title as scholarship_title, u.first_name, u.last_name, u.email FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id LEFT JOIN users u ON a.user_id = u.id';
$where = [];
$params = [];
if ($statusFilter) { $where[] = 'a.status = :status'; $params[':status']=$statusFilter; }
if ($q) { $where[] = '(s.title LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q)'; $params[':q']='%'.$q.'%'; }
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.created_at DESC LIMIT 200';

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
  <h1>📋 Applications Queue</h1>
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
      <button type="submit" class="btn btn-primary">🔍 Filter</button>
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
            <td><small><?= date('M d, Y', strtotime($a['created_at'])) ?></small></td>
            <td>
              <a href="application_view.php?id=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">👁️ View</a>
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
                <button type="submit" class="btn btn-primary btn-sm">✓ Update</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📋</div>
      <h3 class="empty-state-title">No Applications Found</h3>
      <p class="empty-state-description">No applications match your search criteria.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
        $message = 'Your application (ID ' . $chgId . ') status has been updated to ' . $newStatus . '.';
        if ($comments) $message .= ' Comments: ' . $comments;
        $notif->execute([
          ':user_id' => $row['user_id'],
          ':title' => $title,
          ':message' => $message,
          ':type' => 'info',
          ':app_id' => $chgId,
          ':sch_id' => $row['scholarship_id'] ?? null
        ]);

        // Send/queue an email to applicant
        try {
          $userStmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id');
          $userStmt->execute([':id' => $row['user_id']]);
          $user = $userStmt->fetch(PDO::FETCH_ASSOC);
          if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            // get scholarship title if available
            $schTitle = '';
            if (!empty($row['scholarship_id'])) {
              $schS = $pdo->prepare('SELECT title FROM scholarships WHERE id = :id');
              $schS->execute([':id' => $row['scholarship_id']]);
              $sch = $schS->fetch(PDO::FETCH_ASSOC);
              $schTitle = $sch['title'] ?? '';
            }
            // prepare email body using existing template helper if available
            if (function_exists('getApplicationDecisionEmailTemplate')) {
              $emailBody = getApplicationDecisionEmailTemplate($user['first_name'] ?? '', strtolower($schTitle), strtolower($newStatus), $comments);
            } else {
              $emailBody = '<p>Your application status has been updated to ' . htmlspecialchars($newStatus) . '.</p>' . ($comments?'<p>Comments: '.htmlspecialchars($comments).'</p>':'');
            }
            if (function_exists('queueEmail')) {
              queueEmail($user['email'], $title . ' - ' . ($schTitle? $schTitle : ''), $emailBody, $row['user_id']);
            } else {
              // fallback to sendEmail if queue not present
              if (function_exists('sendEmail')) sendEmail($user['email'], $title . ' - ' . ($schTitle? $schTitle : ''), $emailBody, true);
            }
          }
        } catch (Exception $ee) {
          // ignore email failures
        }
          
  if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'] ?? null, 'APPLICATION_STATUS_CHANGED', 'applications', $chgId, 'Status: '.$newStatus.($comments?'; Comments: '.$comments:''));
  $_SESSION['success'] = 'Application status updated to ' . $newStatus . '.';
        }
      } else {
        $_SESSION['flash'] = 'Application not found.';
      }
    } catch (Exception $e) {
      $_SESSION['flash'] = 'Failed to update status.';
    }
  } else {
    $_SESSION['flash'] = 'Invalid status or application id.';
  }
  // redirect back to the view or list
  $redirect = 'applications.php';
  if (!empty($_POST['return_to_view']) && $chgId>0) $redirect = 'applications.php?view=' . $chgId;
  header('Location: ' . $redirect);
  exit;
}

// Handle document verification by staff/admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_document') {
  $docId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
  $newStatus = trim($_POST['new_status'] ?? ''); // expected: verified, rejected, needs_resubmission
  $notes = trim($_POST['notes'] ?? '');
  if ($docId > 0 && in_array($newStatus, ['verified','rejected','needs_resubmission'])) {
    try {
      $dstmt = $pdo->prepare('SELECT d.*, a.user_id, a.scholarship_id FROM documents d LEFT JOIN applications a ON d.application_id = a.id WHERE d.id = :id');
      $dstmt->execute([':id' => $docId]);
      $doc = $dstmt->fetch(PDO::FETCH_ASSOC);
      if ($doc) {
        $upd = $pdo->prepare('UPDATE documents SET verification_status = :vs, verified_by = :vb, verified_at = :vat, notes = :notes WHERE id = :id');
        $vat = $newStatus === 'verified' ? date('Y-m-d H:i:s') : null;
        $upd->execute([':vs' => $newStatus, ':vb' => $_SESSION['user_id'], ':vat' => $vat, ':notes' => $notes, ':id' => $docId]);

        // Log audit
        if (function_exists('logAuditTrail')) {
          logAuditTrail($pdo, $_SESSION['user_id'], 'DOCUMENT_VERIFIED', 'documents', $docId, 'Status: '.$newStatus.($notes?'; Notes: '.$notes:''));
        }

        // Notify user
        try {
          $title = 'Document ' . ucfirst($newStatus);
          $msg = 'Your uploaded document "' . ($doc['file_name'] ?? '') . '" has been marked as ' . $newStatus . '.';
          if ($notes) $msg .= ' Notes: ' . $notes;
          $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id) VALUES (:user_id, :title, :message, :type, :app_id, :sch_id)');
          $notif->execute([':user_id' => $doc['user_id'], ':title' => $title, ':message' => $msg, ':type' => 'application', ':app_id' => $doc['application_id'], ':sch_id' => $doc['scholarship_id']]);

          // queue email if possible
          $userStmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id');
          $userStmt->execute([':id' => $doc['user_id']]);
          $u = $userStmt->fetch(PDO::FETCH_ASSOC);
          if ($u && filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $body = '<p>Dear ' . htmlspecialchars($u['first_name'] ?? '') . ',</p><p>' . htmlspecialchars($msg) . '</p>';
            if (function_exists('queueEmail')) queueEmail($u['email'], $title, $body, $doc['user_id']);
          }
        } catch (Exception $e) {
          // ignore notification errors
        }

        $_SESSION['success'] = 'Document status updated.';
      } else {
        $_SESSION['flash'] = 'Document not found.';
      }
    } catch (Exception $e) {
      $_SESSION['flash'] = 'Failed to update document status.';
    }
  } else {
    $_SESSION['flash'] = 'Invalid request.';
  }
  $redir = 'applications.php';
  if (!empty($_POST['return_to_view']) && !empty($_POST['application_id'])) $redir = 'applications.php?view=' . (int)$_POST['application_id'];
  header('Location: ' . $redir);
  exit;
}

// Handle bulk document verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_documents_bulk') {
  $docIds = $_POST['document_ids'] ?? [];
  $newStatus = trim($_POST['new_status'] ?? '');
  $notes = trim($_POST['notes'] ?? '');
  if (!is_array($docIds) || empty($docIds) || !in_array($newStatus, ['verified','rejected','needs_resubmission'])) {
    $_SESSION['flash'] = 'Invalid bulk request.';
    header('Location: documents.php'); exit;
  }
  try {
    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE documents SET verification_status = :vs, verified_by = :vb, verified_at = :vat, notes = :notes WHERE id = :id');
    $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id) VALUES (:user_id, :title, :message, :type, :app_id, :sch_id)');
    $userStmt = $pdo->prepare('SELECT user_id, application_id, file_name FROM documents WHERE id = :id');
    foreach ($docIds as $d) {
      $id = (int)$d;
      $userStmt->execute([':id' => $id]);
      $row = $userStmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) continue;
      $vat = $newStatus === 'verified' ? date('Y-m-d H:i:s') : null;
      $upd->execute([':vs' => $newStatus, ':vb' => $_SESSION['user_id'], ':vat' => $vat, ':notes' => $notes, ':id' => $id]);

      // Audit
      if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'], 'DOCUMENT_BULK_VERIFICATION', 'documents', $id, 'Status: '.$newStatus.'; Notes: '.$notes);

      // Notification
      $title = 'Document ' . ucfirst($newStatus);
      $msg = 'Your document "' . ($row['file_name'] ?? '') . '" has been marked as ' . $newStatus . '.';
      if ($notes) $msg .= ' Notes: ' . $notes;
      $notif->execute([':user_id' => $row['user_id'], ':title' => $title, ':message' => $msg, ':type' => 'application', ':app_id' => $row['application_id'], ':sch_id' => null]);
    }
    $pdo->commit();
    $_SESSION['success'] = 'Bulk document update completed.';
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = 'Bulk update failed.';
  }
  header('Location: documents.php'); exit;
}

// Handle bulk application status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_change_status') {
  $appIds = $_POST['application_ids'] ?? [];
  $newStatus = trim($_POST['new_status'] ?? '');
  $notes = trim($_POST['notes'] ?? '');
  if (!is_array($appIds) || empty($appIds) || !in_array($newStatus, ['under_review','approved','rejected','pending','submitted','withdrawn'])) {
    $_SESSION['flash'] = 'Invalid bulk status request.';
    header('Location: pending_applications.php'); exit;
  }
  try {
    $pdo->beginTransaction();
    $upd = $pdo->prepare('UPDATE applications SET status = :status, updated_at = NOW() WHERE id = :id');
    $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id) VALUES (:user_id, :title, :message, :type, :app_id, :sch_id)');
    $sel = $pdo->prepare('SELECT user_id, scholarship_id FROM applications WHERE id = :id');
    foreach ($appIds as $aid) {
      $id = (int)$aid;
      $sel->execute([':id' => $id]);
      $row = $sel->fetch(PDO::FETCH_ASSOC);
      if (!$row) continue;
      $upd->execute([':status' => $newStatus, ':id' => $id]);
      if (function_exists('logAuditTrail')) logAuditTrail($pdo, $_SESSION['user_id'], 'APPLICATION_BULK_STATUS', 'applications', $id, 'Status: '.$newStatus.'; Notes: '.$notes);
      $title = 'Application Status Updated';
      $msg = 'Your application (ID ' . $id . ') status has been updated to ' . $newStatus . '.';
      if ($notes) $msg .= ' Comments: ' . $notes;
      $notif->execute([':user_id' => $row['user_id'], ':title' => $title, ':message' => $msg, ':type' => 'application', ':app_id' => $id, ':sch_id' => $row['scholarship_id']]);
    }
    $pdo->commit();
    $_SESSION['success'] = 'Bulk status update completed.';
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = 'Bulk update failed.';
  }
  header('Location: pending_applications.php'); exit;
}

// Check if viewing details
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_dir = $_GET['dir'] ?? 'desc';
$applicant_filter = $_GET['applicant'] ?? '';
$scholarship_filter = $_GET['scholarship'] ?? '';
$viewingApp = null;
if ($viewId) {
    $stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.description as scholarship_desc, s.organization, u.first_name, u.last_name, u.email
                           FROM applications a 
                           LEFT JOIN scholarships s ON a.scholarship_id = s.id
                           LEFT JOIN users u ON a.user_id = u.id
                           WHERE a.id = :aid');
    $stmt->execute([':aid' => $viewId]);
    $viewingApp = $stmt->fetch();
}

if (!$viewId) {
  $allowed_sort = ['created_at','status','scholarship_title','username'];
  $allowed_dir = ['asc','desc'];
  $params = [];
  $sql = 'SELECT a.*, u.username, u.first_name, u.last_name, s.title AS scholarship_title FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN scholarships s ON a.scholarship_id = s.id';
  $where = [];
  if ($status_filter && in_array($status_filter, ['Pending', 'Submitted', 'Approved', 'Completed', 'Rejected'])) {
    $where[] = 'a.status = :status';
    $params['status'] = $status_filter;
  }
  if ($applicant_filter) {
    $where[] = '(u.first_name LIKE :applicant OR u.last_name LIKE :applicant OR u.username LIKE :applicant)';
    $params['applicant'] = "%$applicant_filter%";
  }
  if ($scholarship_filter) {
    $where[] = 's.title LIKE :scholarship';
    $params['scholarship'] = "%$scholarship_filter%";
  }
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY ' . (in_array($sort_by,$allowed_sort)?$sort_by:'created_at') . ' ' . (in_array($sort_dir,$allowed_dir)?$sort_dir:'desc');
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $apps = $stmt->fetchAll();

  // Statistics for summary
  $stats = $pdo->query('SELECT status, COUNT(*) as count FROM applications GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
}

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>