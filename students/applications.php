<?php
/**
 * STUDENT — MY APPLICATIONS
 * Role: Student
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireRole('student', 'Student access required');

$pdo     = getPDO();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request.';
        header('Location: applications.php'); exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'withdraw') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("UPDATE applications SET status='withdrawn' WHERE id=:id AND user_id=:uid AND status NOT IN ('approved','rejected','withdrawn')");
            $stmt->execute([':id'=>$id,':uid'=>$user_id]);
            $_SESSION['success'] = $stmt->rowCount() ? 'Application withdrawn.' : 'Cannot withdraw this application.';
        }
        header('Location: applications.php'); exit;
    }
    if ($action === 'delete_draft') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM applications WHERE id=:id AND user_id=:uid AND status='draft'");
            $stmt->execute([':id'=>$id,':uid'=>$user_id]);
            $_SESSION['success'] = $stmt->rowCount() ? 'Draft deleted.' : 'Draft not found.';
        }
        header('Location: applications.php'); exit;
    }
}

$viewId     = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewingApp = null;
$timeline   = [];

if ($viewId) {
    $stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.description as scholarship_desc, s.organization
                           FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id
                           WHERE a.id=:aid AND a.user_id=:uid');
    $stmt->execute([':aid'=>$viewId,':uid'=>$user_id]);
    $viewingApp = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($viewingApp) {
        try {
            $submittedAt = $viewingApp['submitted_at'] ?? $viewingApp['created_at'] ?? null;
            if ($submittedAt) $timeline[] = ['label'=>'Application Submitted','ts'=>$submittedAt];
            $uploadedStmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE application_id=:aid');
            $uploadedStmt->execute([':aid'=>$viewingApp['id']]);
            $uploadedCount = (int)$uploadedStmt->fetchColumn();
            if ($uploadedCount > 0) $timeline[] = ['label'=>'Documents Uploaded','ts'=>null,'note'=>"$uploadedCount file(s)"];
            if ($viewingApp['reviewed_at']) $timeline[] = ['label'=>'Application Reviewed','ts'=>$viewingApp['reviewed_at']];
            if (in_array(strtolower($viewingApp['status']),['approved','rejected'])) {
                $timeline[] = ['label'=>'Decision Released','ts'=>$viewingApp['updated_at']??$viewingApp['reviewed_at']??null,'note'=>ucfirst($viewingApp['status'])];
            }
        } catch (Exception $e) { $timeline = []; }
    }
}

$stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.organization
                       FROM applications a LEFT JOIN scholarships s ON a.scholarship_id=s.id
                       WHERE a.user_id=:uid ORDER BY a.created_at DESC');
$stmt->execute([':uid'=>$user_id]);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Applications - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
  <div>
    <h1>My Applications</h1>
    <p>Track and manage your scholarship applications</p>
  </div>
  <a href="apply_scholarship.php" class="btn btn-primary" style="position:relative;z-index:1;">+ New Application</a>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if ($viewingApp): ?>
  <!-- Detail View -->
  <div class="content-card" style="margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h3 style="margin:0;"><?= htmlspecialchars($viewingApp['scholarship_title'] ?? 'Application') ?></h3>
      <a href="applications.php" class="btn btn-ghost btn-sm">&larr; Back</a>
    </div>
    <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
      <span class="status-badge status-<?= strtolower($viewingApp['status']) ?>"><?= ucfirst(str_replace('_',' ',$viewingApp['status'])) ?></span>
      <span style="font-size:0.8rem;color:#9E9E9E;">Submitted: <?= date('M d, Y', strtotime($viewingApp['created_at'])) ?></span>
      <?php if ($viewingApp['organization']): ?>
        <span style="font-size:0.8rem;color:#E53935;"><?= htmlspecialchars($viewingApp['organization']) ?></span>
      <?php endif; ?>
    </div>

    <?php if (strtolower($viewingApp['status']) === 'rejected'): ?>
      <?php
        try {
          $rejStmt = $pdo->prepare("SELECT message FROM notifications WHERE user_id=:uid AND related_application_id=:aid AND title LIKE '%Rejected%' ORDER BY created_at DESC LIMIT 1");
          $rejStmt->execute([':uid'=>$user_id,':aid'=>$viewingApp['id']]);
          $rejMsg = $rejStmt->fetchColumn();
        } catch (Exception $e) { $rejMsg = null; }
      ?>
      <?php if ($rejMsg): ?>
        <div class="alert alert-error" style="margin-bottom:1rem;">
          <strong>Rejection Notice:</strong> <?= htmlspecialchars($rejMsg) ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (strtolower($viewingApp['status']) === 'waitlisted'): ?>
      <div class="alert alert-warning" style="margin-bottom:1rem;">
        <strong>You are on the waitlist.</strong> You may be promoted if a slot becomes available.
      </div>
    <?php endif; ?>

    <?php if (!empty($timeline)): ?>
      <h4 style="margin-bottom:0.75rem;font-size:0.9375rem;">Application Timeline</h4>
      <div style="border-left:3px solid #D1D5DB;padding-left:1.25rem;margin-bottom:1.25rem;">
        <?php foreach ($timeline as $t): ?>
          <div style="position:relative;margin-bottom:1rem;">
            <div style="position:absolute;left:-1.5rem;top:0.2rem;width:10px;height:10px;border-radius:50%;background:#E53935;"></div>
            <div style="font-weight:600;font-size:0.875rem;color:#1a1a2e;"><?= htmlspecialchars($t['label']) ?></div>
            <?php if (!empty($t['note'])): ?><div style="font-size:0.8rem;color:#9E9E9E;"><?= htmlspecialchars($t['note']) ?></div><?php endif; ?>
            <?php if (!empty($t['ts'])): ?><div style="font-size:0.75rem;color:#BDBDBD;"><?= date('M d, Y H:i', strtotime($t['ts'])) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php
      $studentDocs = [];
      try {
        $dstmt = $pdo->prepare('SELECT id, document_type, file_name, file_path, verification_status, uploaded_at FROM documents WHERE application_id=:aid');
        $dstmt->execute([':aid'=>$viewingApp['id']]);
        $studentDocs = $dstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Exception $e) {}
    ?>
    <?php if (!empty($studentDocs)): ?>
      <h4 style="margin-bottom:0.75rem;font-size:0.9375rem;">Submitted Documents</h4>
      <table class="modern-table">
        <thead><tr><th>File</th><th>Type</th><th>Status</th><th>Uploaded</th></tr></thead>
        <tbody>
          <?php foreach ($studentDocs as $doc): ?>
            <tr>
              <td><a href="document_view.php?id=<?= (int)$doc['id'] ?>" target="_blank" style="color:#E53935;"><?= htmlspecialchars($doc['file_name']) ?></a></td>
              <td><?= htmlspecialchars($doc['document_type']) ?></td>
              <td><span class="status-badge status-<?= strtolower($doc['verification_status'] ?? 'pending') ?>"><?= htmlspecialchars($doc['verification_status'] ?? 'pending') ?></span></td>
              <td style="font-size:0.8rem;color:#9E9E9E;"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<?php else: ?>
  <!-- List View -->
  <div class="content-card">
    <?php if (empty($apps)): ?>
      <div class="empty-state">
        <div class="empty-state-icon" style="font-size:3rem;">&#128221;</div>
        <div class="empty-state-title">No Applications Yet</div>
        <div class="empty-state-description">You haven't submitted any scholarship applications yet.</div>
        <a href="apply_scholarship.php" class="btn btn-primary" style="margin-top:1rem;">Apply for Scholarship</a>
      </div>
    <?php else: ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h3 style="margin:0;">All Applications (<?= count($apps) ?>)</h3>
      </div>
      <table class="modern-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Scholarship</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apps as $a): ?>
            <tr>
              <td style="color:#9E9E9E;font-size:0.8rem;"><?= $a['id'] ?></td>
              <td>
                <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($a['scholarship_title'] ?? 'General Application') ?></div>
                <?php if ($a['organization']): ?><div style="font-size:0.75rem;color:#E53935;"><?= htmlspecialchars($a['organization']) ?></div><?php endif; ?>
              </td>
              <td><span class="status-badge status-<?= strtolower($a['status']) ?>"><?= ucfirst(str_replace('_',' ',$a['status'])) ?></span></td>
              <td style="font-size:0.8rem;color:#9E9E9E;"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
              <td>
                <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                  <a href="applications.php?view=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                  <?php if ($a['status'] === 'draft'): ?>
                    <a href="apply_scholarship.php?scholarship_id=<?= (int)$a['scholarship_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this draft?')">
                      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                      <input type="hidden" name="action" value="delete_draft">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                  <?php elseif (in_array($a['status'],['submitted','pending','under_review'])): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Withdraw this application?')">
                      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                      <input type="hidden" name="action" value="withdraw">
                      <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                      <button class="btn btn-danger btn-sm">Withdraw</button>
                    </form>
                  <?php elseif ($a['status'] === 'completed'): ?>
                    <?php
                      // Check if already rated
                      $ratedStmt = $pdo->prepare("SELECT id FROM feedback WHERE application_id = :aid");
                      $ratedStmt->execute([':aid' => $a['id']]);
                      $hasRated = $ratedStmt->fetch();
                    ?>
                    <?php if ($hasRated): ?>
                      <span class="btn btn-ghost btn-sm" style="cursor:default;opacity:0.6;">Rated</span>
                    <?php else: ?>
                      <a href="feedback.php?application_id=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Rate</a>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
