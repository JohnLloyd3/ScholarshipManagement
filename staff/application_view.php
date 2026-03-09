<?php
require_once __DIR__ . '/../auth/helpers.php';
require_role(['staff','admin']);
require_once __DIR__ . '/../config/db.php';

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
    reviewer_id INT NOT NULL,
    score INT DEFAULT NULL,
    checklist TEXT DEFAULT NULL,
    comments TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

// Load documents
$dstmt = $pdo->prepare('SELECT * FROM documents WHERE application_id = :id');
$dstmt->execute([':id'=>$id]);
$docs = $dstmt->fetchAll(PDO::FETCH_ASSOC);

// Handle POST: assign/remove/update status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
  if ($action === 'submit_review') {
    $score = isset($_POST['score']) ? (int)$_POST['score'] : null;
    $check = $_POST['checklist'] ?? [];
    $comments = trim($_POST['comments'] ?? '');
    $checkJson = json_encode(array_values($check));
    $ins = $pdo->prepare('INSERT INTO reviews (application_id, reviewer_id, score, checklist, comments) VALUES (:aid, :rid, :score, :check, :comments)');
    $ins->execute([':aid'=>$id, ':rid'=>$_SESSION['user_id'], ':score'=>$score, ':check'=>$checkJson, ':comments'=>$comments]);

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
        $status = trim($_POST['status'] ?? '');
        $allowed = ['submitted','under_review','pending','approved','rejected','waitlisted','draft'];
        if (in_array($status, $allowed, true)) {
            $pdo->prepare('UPDATE applications SET status = :status, reviewed_at = NOW() WHERE id = :id')->execute([':status'=>$status, ':id'=>$id]);
            $_SESSION['success'] = 'Status updated';
        }
    }
    header('Location: application_view.php?id=' . $id);
    exit;
}

?>
<?php
$page_title = 'Application #' . (int)$app['id'] . ' - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📋 Application #<?= (int)$app['id'] ?></h1>
  <p class="text-muted"><?= htmlspecialchars($app['scholarship_title']) ?></p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<a href="applications.php" class="btn btn-secondary" style="margin-bottom:var(--space-xl)">← Back to Queue</a>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Applicant Information</h3>
  <p><strong><?= htmlspecialchars($app['first_name'].' '.$app['last_name']) ?></strong><br><span class="text-muted"><?= htmlspecialchars($app['email']) ?></span></p>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Application Details</h3>
  <div style="display:grid;gap:var(--space-md)">
    <div><strong>Status:</strong> <span class="status-badge status-<?= strtolower($app['status']) ?>"><?= htmlspecialchars($app['status']) ?></span></div>
    <div><strong>Submitted:</strong> <?= htmlspecialchars($app['created_at']) ?></div>
    <div><strong>Last Updated:</strong> <?= htmlspecialchars($app['updated_at'] ?? '—') ?></div>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Submitted Documents</h3>
  <?php if ($docs): ?>
    <ul style="list-style:none;padding:0;margin:0">
      <?php foreach($docs as $d): ?>
        <li style="padding:var(--space-md);border-bottom:1px solid var(--gray-200)">
          <a href="../member/document_view.php?id=<?= (int)$d['id'] ?>" target="_blank" class="text-primary"><?= htmlspecialchars($d['document_type'].' — '.$d['file_name']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="text-muted">No documents uploaded.</p>
  <?php endif; ?>
</div>

<div class="content-card">
  <h3 style="margin-bottom:var(--space-lg)">Submit Review</h3>
  <form method="post">
    <input type="hidden" name="action" value="submit_review">
    
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
    
    <button class="btn btn-primary">✅ Submit Review</button>
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
        <div style="padding:var(--space-lg);background:var(--gray-50);border-radius:var(--radius-lg);border-left:4px solid var(--red-primary)">
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
    <div class="form-group" style="margin:0;flex:1">
      <label class="form-label">New Status</label>
      <select name="status" class="form-select">
        <?php $statuses=['submitted','under_review','pending','approved','rejected','waitlisted','draft']; foreach($statuses as $st) echo '<option '.($app['status']==$st?'selected':'').' value="'.$st.'">'.ucfirst($st).'</option>'; ?>
      </select>
    </div>
    <button class="btn btn-primary">💾 Save Status</button>
  </form>
  
  <?php if (strtolower($app['status']) === 'approved'): ?>
    <?php
    // Check if interview already scheduled
    $interviewStmt = $pdo->prepare('
        SELECT ib.*, s.interview_date, s.interview_time, s.duration_minutes, s.interview_type, s.location, s.meeting_link
        FROM interview_bookings ib
        JOIN interview_slots s ON ib.slot_id = s.id
        WHERE ib.application_id = :app_id
        ORDER BY ib.booked_at DESC
        LIMIT 1
    ');
    $interviewStmt->execute([':app_id' => $id]);
    $interview = $interviewStmt->fetch(PDO::FETCH_ASSOC);
    ?>
    
    <?php if ($interview): ?>
      <div style="margin-top: var(--space-lg); padding: var(--space-lg); background: #e3f2fd; border-radius: var(--radius-lg); border-left: 4px solid #2196F3;">
        <h4 style="margin: 0 0 var(--space-md) 0; color: #1976D2;">📅 Interview Scheduled</h4>
        <div style="display: grid; gap: var(--space-sm); color: #555;">
          <div><strong>Date:</strong> <?= date('F d, Y', strtotime($interview['interview_date'])) ?></div>
          <div><strong>Time:</strong> <?= date('g:i A', strtotime($interview['interview_time'])) ?></div>
          <div><strong>Duration:</strong> <?= (int)$interview['duration_minutes'] ?> minutes</div>
          <div><strong>Type:</strong> <?= ucfirst($interview['interview_type']) ?></div>
          <?php if ($interview['interview_type'] === 'online' && $interview['meeting_link']): ?>
            <div><strong>Meeting Link:</strong> <a href="<?= htmlspecialchars($interview['meeting_link']) ?>" target="_blank" class="text-primary">Join Meeting</a></div>
          <?php elseif ($interview['location']): ?>
            <div><strong>Location:</strong> <?= htmlspecialchars($interview['location']) ?></div>
          <?php endif; ?>
          <div><strong>Status:</strong> <span class="status-badge status-<?= $interview['status'] ?>"><?= ucfirst($interview['status']) ?></span></div>
        </div>
      </div>
    <?php else: ?>
      <div style="margin-top: var(--space-lg); padding: var(--space-lg); background: #e8f5e9; border-radius: var(--radius-lg); border-left: 4px solid #4CAF50;">
        <h4 style="margin: 0 0 var(--space-md) 0; color: #2e7d32;">✅ Application Approved</h4>
        <p style="margin: 0 0 var(--space-md) 0; color: #555;">This applicant is ready for an interview. Schedule an interview slot now.</p>
        <a href="../admin/interview_slots.php?app_id=<?= (int)$app['id'] ?>&scholarship_id=<?= (int)$app['scholarship_id'] ?>" class="btn btn-primary">
          📅 Schedule Interview
        </a>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
