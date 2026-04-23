<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();

requireLogin();
requireAnyRole(['staff', 'admin'], 'Staff or Admin access required');

$pdo = getPDO();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: applications.php'); exit; }

// Load application
$stmt = $pdo->prepare('SELECT a.*, u.first_name, u.last_name, u.email, s.title as scholarship_title FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN scholarships s ON a.scholarship_id = s.id WHERE a.id = :id');
$stmt->execute([':id'=>$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) { header('Location: applications.php'); exit; }

// Ensure reviews table exists
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    reviewer_id INT DEFAULT NULL,
    score INT DEFAULT NULL,
    checklist TEXT DEFAULT NULL,
    comments TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rev_app (application_id),
    INDEX idx_rev_reviewer (reviewer_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
  error_log('[application_view] reviews table: ' . $e->getMessage());
}

// Load documents
$dstmt = $pdo->prepare('SELECT * FROM documents WHERE application_id = :id');
$dstmt->execute([':id'=>$id]);
$docs = $dstmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST: assign/remove/update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: application_view.php?id=' . $id);
        exit;
    }
    $action = $_POST['action'] ?? '';
  if ($action === 'submit_review') {
    $score = isset($_POST['score']) ? (int)$_POST['score'] : null;
    $check = $_POST['checklist'] ?? [];
    $comments = trim($_POST['comments'] ?? '');
    $checkJson = json_encode(array_values($check));
    
    try {
      $ins = $pdo->prepare('INSERT INTO reviews (application_id, reviewer_id, score, checklist, comments) VALUES (:aid, :rid, :score, :check, :comments)');
      $ins->execute([':aid'=>$id, ':rid'=>$_SESSION['user_id'], ':score'=>$score, ':check'=>$checkJson, ':comments'=>$comments]);
    } catch (Exception $e) {
      error_log('[submit_review] ' . $e->getMessage());
      $_SESSION['flash'] = 'Failed to save review. Please try again.';
      header('Location: application_view.php?id=' . $id);
      exit;
    }

    // Create in-app notification for applicant
    try {
      $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, related_scholarship_id) VALUES (:user_id, :title, :message, :type, :app_id, :sch_id)');
      $title = 'Application Reviewed';
      $message = 'Your application (ID ' . $id . ') was reviewed. Score: ' . ($score !== null ? $score : 'N/A') . '.';
      if ($comments) $message .= ' Comments: ' . $comments;
      $notif->execute([':user_id'=>$app['user_id'], ':title'=>$title, ':message'=>$message, ':type'=>'application', ':app_id'=>$id, ':sch_id'=>$app['scholarship_id'] ?? null]);

      // Queue email if queueEmail exists
      $userStmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id');
      $userStmt->execute([':id' => $app['user_id']]);
      $u = $userStmt->fetch(PDO::FETCH_ASSOC);
      if ($u && filter_var($u['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $body = '<p>Dear ' . htmlspecialchars($u['first_name'] ?? '') . ',</p>';
        $body .= '<p>' . htmlspecialchars($message) . '</p>';
        if (function_exists('queueEmail')) {
          queueEmail($u['email'], $title, $body, $app['user_id']);
        } elseif (function_exists('sendEmail')) {
          sendEmail($u['email'], $title, $body, true);
        }
      }
    } catch (Exception $e) {
      // ignore notification/email errors
    }

    $_SESSION['success'] = 'Review submitted.';
    header('Location: application_view.php?id=' . $id);
    exit;
  }
    if ($action === 'update_status') {
        $status        = trim($_POST['status'] ?? '');
        $rejectReason  = trim($_POST['reject_reason'] ?? '');
        $allowed = ['submitted','under_review','pending','approved','rejected','waitlisted','draft'];
        if (in_array($status, $allowed, true)) {
            $pdo->prepare('UPDATE applications SET status = :status, reviewed_at = NOW() WHERE id = :id')
                ->execute([':status' => $status, ':id' => $id]);

            // Notify student with reason on rejection/waitlist
            $notifMsg = match($status) {
                'approved'   => 'Congratulations! Your application for "' . $app['scholarship_title'] . '" has been approved.',
                'rejected'   => 'Your application for "' . $app['scholarship_title'] . '" was not selected.' . ($rejectReason ? ' Reason: ' . $rejectReason : ''),
                'waitlisted' => 'Your application for "' . $app['scholarship_title'] . '" has been waitlisted. You may be considered if a slot opens.',
                default      => 'Your application status has been updated to ' . ucfirst(str_replace('_', ' ', $status)) . '.',
            };
            $notifType = match($status) {
                'approved'   => 'success',
                'rejected'   => 'error',
                'waitlisted' => 'warning',
                default      => 'info',
            };
            try {
                $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_application_id, created_at) VALUES (:uid, :title, :msg, :type, :aid, NOW())')
                    ->execute([':uid' => $app['user_id'], ':title' => 'Application ' . ucfirst($status), ':msg' => $notifMsg, ':type' => $notifType, ':aid' => $id]);
                // Email
                $userRow = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id');
                $userRow->execute([':id' => $app['user_id']]);
                $u = $userRow->fetch(PDO::FETCH_ASSOC);
                if ($u && !empty($u['email'])) {
                    $emailBody = '<p>Dear ' . htmlspecialchars($u['first_name']) . ',</p><p>' . htmlspecialchars($notifMsg) . '</p>';
                    if ($status === 'rejected' && $rejectReason) {
                        $emailBody .= '<p><strong>Reason:</strong> ' . htmlspecialchars($rejectReason) . '</p>';
                    }
                    $emailBody .= '<p>Log in to your account for more details.</p>';
                    queueEmail($u['email'], 'Application ' . ucfirst($status) . ' � ' . $app['scholarship_title'], $emailBody, $app['user_id']);
                }
            } catch (Exception $e) { /* ignore */ }

            // Auto-create disbursement on approval
            if ($status === 'approved') {
                try {
                    $appRow = $pdo->prepare('SELECT scholarship_id, user_id FROM applications WHERE id = :id');
                    $appRow->execute([':id' => $id]);
                    $ar = $appRow->fetch(PDO::FETCH_ASSOC);
                    $schRow = $pdo->prepare('SELECT amount FROM scholarships WHERE id = :id');
                    $schRow->execute([':id' => $ar['scholarship_id']]);
                    $sch = $schRow->fetch(PDO::FETCH_ASSOC);
                    $pdo->prepare("INSERT IGNORE INTO disbursements (application_id, user_id, scholarship_id, amount, disbursement_date, payment_method, status, created_at) VALUES (:app_id, :user_id, :sch_id, :amount, CURDATE(), 'Cash', 'pending', NOW())")
                        ->execute([':app_id' => $id, ':user_id' => $ar['user_id'], ':sch_id' => $ar['scholarship_id'], ':amount' => $sch['amount'] ?? 0]);
                } catch (Exception $e) { error_log('[Disbursement] ' . $e->getMessage()); }
            }

            $_SESSION['success'] = 'Status updated to ' . ucfirst(str_replace('_', ' ', $status)) . '.';
        }
    }
    header('Location: application_view.php?id=' . $id);
    exit;
}

?>
<?php
$page_title = 'Application #' . (int)$app['id'] . ' - ScholarHub';
$base_path = '../';
$csrf_token = generateCSRFToken();
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>Application #<?= (int)$app['id'] ?></h1>
  <p class="text-muted"><?= htmlspecialchars($app['scholarship_title']) ?></p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<a href="applications.php" class="btn btn-secondary" style="margin-bottom:var(--space-xl)">? Back to Queue</a>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Applicant Information</h3>
  <p><strong><?= htmlspecialchars($app['first_name'].' '.$app['last_name']) ?></strong><br><span class="text-muted"><?= htmlspecialchars($app['email']) ?></span></p>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Application Details</h3>
  <div style="display:grid;gap:var(--space-md)">
    <div><strong>Status:</strong> <span class="status-badge status-<?= strtolower($app['status']) ?>"><?= htmlspecialchars($app['status']) ?></span></div>
    <div><strong>Submitted:</strong> <?= htmlspecialchars($app['created_at']) ?></div>
    <div><strong>Last Updated:</strong> <?= htmlspecialchars($app['updated_at'] ?? '�') ?></div>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Submitted Documents</h3>
  <?php if ($docs): ?>
    <ul style="list-style:none;padding:0;margin:0">
      <?php foreach($docs as $d): ?>
        <li style="padding:var(--space-md);border-bottom:1px solid var(--gray-200)">
          <a href="../students/document_view.php?id=<?= (int)$d['id'] ?>" target="_blank" class="text-primary"><?= htmlspecialchars($d['document_type'].' � '.$d['file_name']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
  <?php endif; ?>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Submit Review</h3>
  <form method="post">
    <input type="hidden" name="action" value="submit_review">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    
    <div class="form-group">
      <label class="form-label">Score (0-100) *</label>
      <input type="number" name="score" min="0" max="100" class="form-input" required>
    </div>
    
    <div class="form-group">
      <label class="form-label">Checklist</label>
      <div style="display:grid;gap:var(--space-sm)">
        <label style="display:flex;align-items:center;gap:var(--space-sm)"><input type="checkbox" name="checklist[]" value="Eligibility"> Eligibility</label>
        <label style="display:flex;align-items:center;gap:var(--space-sm)"><input type="checkbox" name="checklist[]" value="Academic Merit"> Academic Merit</label>
        <label style="display:flex;align-items:center;gap:var(--space-sm)"><input type="checkbox" name="checklist[]" value="Financial Need"> Financial Need</label>
        <label style="display:flex;align-items:center;gap:var(--space-sm)"><input type="checkbox" name="checklist[]" value="Documents Complete"> Documents Complete</label>
      </div>
    </div>
    
    <div class="form-group">
      <label class="form-label">Comments (Optional)</label>
      <textarea name="comments" rows="4" class="form-input" placeholder="Add your review comments here..."></textarea>
    </div>
    
    <button class="btn btn-primary">? Submit Review</button>
  </form>

  <?php
    // Fetch reviews if table exists
    $reviews = [];
    try {
      $rlist = $pdo->prepare('SELECT r.*, u.first_name, u.last_name FROM reviews r LEFT JOIN users u ON r.reviewer_id = u.id WHERE r.application_id = :aid ORDER BY r.created_at DESC');
      $rlist->execute([':aid'=>$id]);
      $reviews = $rlist->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
      // Reviews table doesn't exist yet - skip this section
      $reviews = [];
    }
  ?>
  
  <?php if ($reviews): ?>
    <h4 style="margin-top:var(--space-2xl);margin-bottom:var(--space-lg)">Previous Reviews</h4>
    <div style="display:grid;gap:var(--space-lg)">
      <?php foreach($reviews as $rev): ?>
        <div style="padding:var(--space-lg);background:var(--gray-50);border-radius:var(--r-lg);border-left:4px solid var(--peach)">
          <div style="display:flex;justify-content:space-between;margin-bottom:var(--space-md)">
            <strong><?= htmlspecialchars($rev['first_name'].' '.$rev['last_name'] ?? 'Staff') ?></strong>
            <span class="status-badge status-approved">Score: <?= htmlspecialchars($rev['score'] ?? 'N/A') ?></span>
          </div>
          <div class="text-muted" style="font-size:0.875rem;margin-bottom:var(--space-sm)"><?= htmlspecialchars($rev['created_at']) ?></div>
          <div style="margin-bottom:var(--space-sm)"><strong>Checklist:</strong> <?= htmlspecialchars($rev['checklist']) ?></div>
          <?php if (!empty($rev['comments'])): ?>
            <div><strong>Comments:</strong> <?= nl2br(htmlspecialchars($rev['comments'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Update Application Status</h3>
  <form method="post" style="display:flex;gap:var(--space-md);align-items:end">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="form-group" style="margin:0;flex:1">
      <label class="form-label">New Status</label>
      <select name="status" class="form-select">
        <?php $statuses=['submitted','under_review','pending','approved','rejected','waitlisted','draft']; foreach($statuses as $st) echo '<option '.($app['status']==$st?'selected':'').' value="'.$st.'">'.ucfirst($st).'</option>'; ?>
      </select>
    </div>
    <button class="btn btn-primary">Save Status</button>
  </form>
  
  <?php if (strtolower($app['status']) === 'approved'): ?>
    <?php
    // Check if applicant has been assigned to an interview group
    $interviewStmt = $pdo->prepare('
        SELECT 
            ia.*,
            g.group_code,
            s.session_date,
            s.time_block,
            s.time_start,
            s.time_end
        FROM interview_assignments ia
        JOIN interview_groups g ON ia.group_id = g.id
        JOIN interview_sessions s ON g.session_id = s.id
        WHERE ia.application_id = :app_id
        LIMIT 1
    ');
    $interviewStmt->execute([':app_id' => $id]);
    $interview = $interviewStmt->fetch(PDO::FETCH_ASSOC);
    ?>
    <?php if ($interview && $interview['final_status'] === 'completed'): ?>
      <div style="margin-top:var(--space-lg);padding:var(--space-lg);background:#e8f5e9;border-radius:var(--r-lg);border-left:4px solid #4CAF50;">
        <h4 style="margin:0 0 var(--space-sm) 0;color:#2e7d32;">✓ Interview Completed</h4>
        <p style="margin:0;color:#555;">Interview was completed on <?= date('M d, Y', strtotime($interview['session_date'])) ?>. Disbursement is pending.</p>
      </div>
    <?php elseif ($interview): ?>
      <div style="margin-top:var(--space-lg);padding:var(--space-lg);background:#e3f2fd;border-radius:var(--r-lg);border-left:4px solid #2196F3;">
        <h4 style="margin:0 0 0.75rem 0;color:#1976D2;">Interview Assigned</h4>
        <div style="display:grid;gap:var(--space-sm);color:#555;">
          <div><strong>Date:</strong> <?= date('F d, Y', strtotime($interview['session_date'])) ?></div>
          <div><strong>Session:</strong> <?= $interview['time_block'] === 'AM' ? 'Morning' : 'Afternoon' ?> (<?= date('g:i A', strtotime($interview['time_start'])) ?> - <?= date('g:i A', strtotime($interview['time_end'])) ?>)</div>
          <div><strong>Group:</strong> <?= htmlspecialchars($interview['group_code']) ?></div>
          <div><strong>Attendance:</strong> <span class="status-badge status-<?= $interview['attendance_status'] ?>"><?= ucfirst($interview['attendance_status']) ?></span></div>
          <div><strong>Progress:</strong> Orientation: <?= ucfirst($interview['orientation_status']) ?>, Interview: <?= ucfirst($interview['interview_status']) ?></div>
        </div>
      </div>
    <?php else: ?>
      <div style="margin-top:var(--space-lg);padding:var(--space-lg);background:#fff8e1;border-radius:var(--r-lg);border-left:4px solid #FFC107;">
        <h4 style="margin:0 0 0.5rem 0;color:#e65100;">No Interview Assigned Yet</h4>
        <p style="margin:0 0 1rem 0;color:#555;">Use Interview Management to auto-assign this applicant to an interview group.</p>
        <a href="../staff/interview_management.php?scholarship_id=<?= (int)$app['scholarship_id'] ?>" class="btn btn-primary">Go to Interview Management</a>
      </div>
    <?php endif; ?>

  <?php elseif (strtolower($app['status']) === 'under_review'): ?>
    <?php
    $reviewedAt = $app['reviewed_at'] ?? $app['updated_at'] ?? null;
    $daysUnderReview = $reviewedAt ? (int)floor((time() - strtotime($reviewedAt)) / 86400) : 0;
    $urgentColor = $daysUnderReview >= 7 ? '#dc2626' : ($daysUnderReview >= 3 ? '#d97706' : '#2563eb');
    ?>
    <div style="margin-top:var(--space-lg);padding:var(--space-lg);background:#eff6ff;border-radius:var(--r-lg);border-left:4px solid <?= $urgentColor ?>;">
      <h4 style="margin:0 0 var(--space-sm) 0;color:<?= $urgentColor ?>;">
        ? Under Review � <?= $daysUnderReview ?> day<?= $daysUnderReview !== 1 ? 's' : '' ?> waiting
        <?php if ($daysUnderReview >= 7): ?> <span style="font-size:0.8rem;font-weight:400;">(Action recommended)</span><?php endif; ?>
      </h4>
      <p style="margin:0 0 var(--space-md) 0;color:#555;">Make a final decision on this application.</p>
      <div style="display:flex;gap:var(--space-md);flex-wrap:wrap;">
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="status" value="approved">
          <button type="submit" class="btn btn-primary" onclick="return confirm('Approve this application? A disbursement will be created automatically.')">? Approve</button>
        </form>
        <button type="button" class="btn btn-ghost" style="color:#dc2626;" onclick="document.getElementById('rejectModal').style.display='block'">? Reject</button>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="status" value="waitlisted">
          <button type="submit" class="btn btn-ghost" onclick="return confirm('Waitlist this application?')">? Waitlist</button>
        </form>
      </div>
    </div>

    <!-- Reject with reason modal -->
    <div id="rejectModal" class="modal" style="display:none;">
      <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
          <h2>? Reject Application</h2>
          <span class="modal-close" onclick="document.getElementById('rejectModal').style.display='none'">&times;</span>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="status" value="rejected">
          <div class="form-group">
            <label class="form-label">Reason for Rejection <small class="text-muted">(shown to student)</small></label>
            <textarea name="reject_reason" class="form-textarea" rows="4" placeholder="e.g. Does not meet GPA requirement, incomplete documents, etc." required></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('rejectModal').style.display='none'">Cancel</button>
            <button type="submit" class="btn btn-primary" style="background:#dc2626;border-color:#dc2626;">Confirm Rejection</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
