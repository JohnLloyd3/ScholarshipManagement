<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../config/email.php';

startSecureSession();

// Authentication
requireLogin();
requireAnyRole(['admin', 'staff'], 'Access Denied');

$pdo = getPDO();
$action = $_GET['action'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle POST requests (approve/reject/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = 'Invalid request (CSRF token missing or incorrect).';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $post_action = $_POST['action'] ??  '';
    $app_id = sanitizeInt($_POST['app_id'] ?? 0);
    
    if ($post_action === 'approve') {
        try {
            $stmt = $pdo->prepare(" 
                UPDATE applications 
                SET status = 'approved', reviewed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $app_id]);
            
            // Get application details for notification
            $appStmt = $pdo->prepare("
                SELECT a.*, u.email, u.first_name, s.title as scholarship_title
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN scholarships s ON a.scholarship_id = s.id
                WHERE a.id = :id
            ");
            $appStmt->execute([':id' => $app_id]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_application_id)
                VALUES (:user_id, 'Application Approved', :message, 'success', :app_id)
            ");
            $notifStmt->execute([
                ':user_id' => $app['user_id'],
                ':message' => 'Congratulations! Your application for ' . $app['scholarship_title'] . ' has been approved.',
                ':app_id' => $app_id
            ]);
            
            // Queue email
            $emailSubject = 'Application Approved - ' . $app['scholarship_title'];
            $emailBody = "<h2>Application Approved</h2><p>Dear " . htmlspecialchars($app['first_name']) . ",</p><p>Congratulations! Your application for <strong>" . htmlspecialchars($app['scholarship_title']) . "</strong> has been approved.</p><p>Please log in to your account for more details.</p>";
            queueEmail($app['email'], $emailSubject, $emailBody, $app['user_id']);
            
            // Auto-create pending disbursement
            try {
                $schStmt = $pdo->prepare("SELECT amount FROM scholarships WHERE id = :id");
                $schStmt->execute([':id' => $app['scholarship_id']]);
                $sch = $schStmt->fetch(PDO::FETCH_ASSOC);
                $amount = $sch['amount'] ?? 0;
                $disbStmt = $pdo->prepare("INSERT IGNORE INTO disbursements (application_id, user_id, scholarship_id, amount, disbursement_date, status, created_at) VALUES (:app_id, :user_id, :sch_id, :amount, CURDATE(), 'pending', NOW())");
                $disbStmt->execute([':app_id' => $app_id, ':user_id' => $app['user_id'], ':sch_id' => $app['scholarship_id'], ':amount' => $amount]);
            } catch (Exception $e) { error_log('[Disbursement] ' . $e->getMessage()); }
            
            $_SESSION['message'] = 'Application approved!';
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }
    } elseif ($post_action === 'reject') {
        try {
            $stmt = $pdo->prepare(" 
                UPDATE applications 
                SET status = 'rejected', reviewed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $app_id]);
            
            // Get application details
            $appStmt = $pdo->prepare("
                SELECT a.*, u.email, u.first_name, s.title as scholarship_title
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN scholarships s ON a.scholarship_id = s.id
                WHERE a.id = :id
            ");
            $appStmt->execute([':id' => $app_id]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);
            
            // Create notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_application_id)
                VALUES (:user_id, 'Application Rejected', :message, 'error', :app_id)
            ");
            $notifStmt->execute([
                ':user_id' => $app['user_id'],
                ':message' => 'Unfortunately, your application for ' . $app['scholarship_title'] . ' was not approved this time.',
                ':app_id' => $app_id
            ]);
            
            // Queue email
            $emailSubject = 'Application Update - ' . $app['scholarship_title'];
            queueEmail($app['email'], $emailSubject,
                "<h2>Application Status</h2><p>Dear " . htmlspecialchars($app['first_name']) . ",</p><p>Unfortunately, your application was not selected this round. Keep trying!</p>", $app['user_id']);
            
            $_SESSION['message'] = 'Application rejected!';
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }
    }
}

function fraudScoreBadge(int $score): string {
    if ($score <= 0) return '';
    if ($score <= 30) {
        $color = '#16a34a'; $bg = '#dcfce7'; $label = 'Low';
    } elseif ($score <= 60) {
        $color = '#ca8a04'; $bg = '#fef9c3'; $label = 'Medium';
    } else {
        $color = '#dc2626'; $bg = '#fee2e2'; $label = 'High';
    }
    return '<span title="Fraud Score: ' . $score . '" style="display:inline-block;padding:2px 7px;border-radius:9999px;font-size:0.75rem;font-weight:600;background:' . $bg . ';color:' . $color . ';">' . $score . ' ' . $label . '</span>';
}

// Fetch applications based on user role
$query = "
    SELECT a.*, u.first_name, u.last_name, u.email, s.title as scholarship_title,
           COALESCE(a.fraud_score, 0) as fraud_score
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.status != 'draft'
    ORDER BY a.status ASC, a.submitted_at DESC
";

$applications = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch single application for viewing
$viewing = null;
if ($action === 'view') {
    $id = sanitizeInt($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT a.*, u.first_name, u.last_name, u.email, s.title as scholarship_title, s.description as scholarship_desc
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN scholarships s ON a.scholarship_id = s.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $viewing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get documents
        if ($viewing) {
            $docStmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = :id");
            $docStmt->execute([':id' => $id]);
            $viewing['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
}
?>
<?php
$page_title = 'Applications Management - Admin';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📝 Applications Management</h1>
  <p class="text-muted">Review and manage scholarship applications</p>
</div>
<?php if ($message): ?>
  <div class="alert alert-success"><?= sanitizeString($message) ?></div>
<?php endif; ?>

<?php if ($action === 'view' && $viewing): ?>
  <div style="margin-bottom: var(--space-lg);">
    <a href="applications.php" class="btn btn-ghost">← Back to Applications</a>
  </div>
  
  <div class="content-card" style="border-left: 4px solid var(--red-primary);">
    <h2 style="margin-bottom: var(--space-xl);"><?= sanitizeString($viewing['scholarship_title']) ?></h2>
    
    <div style="display: grid; gap: var(--space-lg);">
      <div>
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-xs);">Applicant</label>
        <div><?= sanitizeString($viewing['first_name'] . ' ' . $viewing['last_name']) ?></div>
      </div>
      
      <div>
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-xs);">Email</label>
        <div><?= sanitizeString($viewing['email']) ?></div>
      </div>
      
      <div>
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-xs);">Application Title</label>
        <div><?= sanitizeString($viewing['title'] ?? 'N/A') ?></div>
      </div>
      
      <div>
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-xs);">GPA</label>
        <div><?= $viewing['gpa'] ?? 'N/A' ?></div>
      </div>
      
      <div>
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-xs);">Status</label>
        <div><span class="status-badge status-<?= $viewing['status'] ?>"><?= $viewing['status'] ?></span></div>
      </div>
      
      <div>
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-xs);">Application Details</label>
        <div style="background: var(--gray-50); padding: var(--space-md); border-radius: var(--radius-md);"><?= nl2br(sanitizeString($viewing['details'] ?? '')) ?></div>
      </div>
    </div>
                
    <?php if (!empty($viewing['documents'])): ?>
      <div style="margin-top: var(--space-xl);">
        <label style="font-weight: 600; color: var(--gray-700); display: block; margin-bottom: var(--space-md);">Uploaded Documents</label>
        <table class="modern-table">
          <thead>
            <tr>
              <th>Document Type</th>
              <th>File Name</th>
              <th>Uploaded</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($viewing['documents'] as $doc): ?>
              <tr>
                <td><?= sanitizeString($doc['document_type'] ?? 'Unknown') ?></td>
                <td><?= sanitizeString($doc['file_name']) ?></td>
                <td><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                <td>
                  <a href="../<?= $doc['file_path'] ?>" target="_blank" class="btn btn-primary btn-sm">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
                
    <?php if ($viewing['status'] === 'under_review' || $viewing['status'] === 'submitted'): ?>
      <div style="margin-top: var(--space-2xl); padding-top: var(--space-xl); border-top: 1px solid var(--gray-200);">
        <h3 style="margin-bottom: var(--space-lg);">Decision</h3>
        <div style="display: flex; gap: var(--space-md);">
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="app_id" value="<?= $viewing['id'] ?>">
            <button type="submit" class="btn btn-primary">✓ Approve</button>
          </form>
          
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="app_id" value="<?= $viewing['id'] ?>">
            <button type="submit" class="btn btn-ghost">✗ Reject</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="content-card">
    <h3 style="margin-bottom: var(--space-xl);">All Applications</h3>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Applicant</th>
          <th>Scholarship</th>
          <th>Status</th>
          <th>Fraud</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($applications)): ?>
          <?php foreach ($applications as $app): ?>
            <tr>
              <td><?= sanitizeString($app['first_name'] . ' ' . $app['last_name']) ?></td>
              <td><?= sanitizeString($app['scholarship_title']) ?></td>
              <td><span class="status-badge status-<?= $app['status'] ?>"><?= $app['status'] ?></span></td>
              <td><?= fraudScoreBadge((int)($app['fraud_score'] ?? 0)) ?></td>
              <td><?= date('M d, Y', strtotime($app['submitted_at'] ?? $app['created_at'])) ?></td>
              <td>
                <a href="../staff/application_view.php?id=<?= $app['id'] ?>" class="btn btn-primary btn-sm">View & Review</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3 class="empty-state-title">No Applications</h3>
                <p class="empty-state-description">No applications to review at this time.</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
