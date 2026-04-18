<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request.';
        header('Location: applications.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'withdraw') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'withdrawn' WHERE id = :id AND user_id = :uid AND status NOT IN ('approved','rejected','withdrawn')");
            $stmt->execute([':id' => $id, ':uid' => $user_id]);
            $_SESSION['success'] = $stmt->rowCount() ? 'Application withdrawn.' : 'Cannot withdraw this application.';
        }
        header('Location: applications.php'); exit;
    }

    if ($action === 'delete_draft') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM applications WHERE id = :id AND user_id = :uid AND status = 'draft'");
            $stmt->execute([':id' => $id, ':uid' => $user_id]);
            $_SESSION['success'] = $stmt->rowCount() ? 'Draft deleted.' : 'Draft not found.';
        }
        header('Location: applications.php'); exit;
    }
}

// Check if viewing details
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : null;
$viewingApp = null;
if ($viewId) {
    $stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.description as scholarship_desc, s.organization 
                           FROM applications a 
                           LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                           WHERE a.id = :aid AND a.user_id = :uid');
    $stmt->execute([':aid' => $viewId, ':uid' => $user_id]);
    $viewingApp = $stmt->fetch();
    if ($viewingApp) {
      // build timeline based on available timestamps and documents
      try {
        // Submitted timestamp
        $submittedAt = $viewingApp['created_at'] ?? $viewingApp['submitted_at'] ?? null;

        // Documents: required vs verified
        $reqCount = 0;
        $reqStmt = $pdo->prepare('SELECT COUNT(*) FROM scholarship_documents WHERE scholarship_id = :sid');
        $reqStmt->execute([':sid' => $viewingApp['scholarship_id']]);
        $reqCount = (int) $reqStmt->fetchColumn();

        $verifiedCount = 0;
        $verStmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE application_id = :aid AND verification_status = "verified"');
        $verStmt->execute([':aid' => $viewingApp['id']]);
        $verifiedCount = (int) $verStmt->fetchColumn();

        // When documents were last verified
        $vAt = null;
        $vAtStmt = $pdo->prepare('SELECT MAX(verified_at) as v FROM documents WHERE application_id = :aid AND verification_status = "verified"');
        $vAtStmt->execute([':aid' => $viewingApp['id']]);
        $vAt = $vAtStmt->fetchColumn();

        // Application reviewed timestamp
        $reviewedAt = $viewingApp['reviewed_at'] ?? null;

        // Decision released (when status became approved/rejected) -> use updated_at
        $decisionAt = null;
        if (in_array(strtolower($viewingApp['status']), ['approved','rejected'])) {
          $decisionAt = $viewingApp['updated_at'] ?? $viewingApp['reviewed_at'] ?? null;
        }

        $timeline = [];
        if ($submittedAt) $timeline[] = ['label' => 'Application Submitted', 'ts' => $submittedAt];
        // documents uploaded (any uploaded)
        $uploadedStmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE application_id = :aid');
        $uploadedStmt->execute([':aid' => $viewingApp['id']]);
        $uploadedCount = (int) $uploadedStmt->fetchColumn();
        if ($uploadedCount > 0) $timeline[] = ['label' => 'Documents Uploaded', 'ts' => null, 'note' => "$uploadedCount file(s)"];
        if ($reqCount > 0) {
          if ($verifiedCount >= $reqCount && $vAt) {
            $timeline[] = ['label' => 'Documents Verified', 'ts' => $vAt];
          } elseif ($verifiedCount > 0) {
            $timeline[] = ['label' => 'Documents Partially Verified', 'ts' => null, 'note' => "$verifiedCount of $reqCount verified"];
          }
        } else {
          if ($verifiedCount > 0 && $vAt) $timeline[] = ['label' => 'Documents Verified', 'ts' => $vAt];
        }
        if ($reviewedAt) $timeline[] = ['label' => 'Application Reviewed', 'ts' => $reviewedAt];
        if ($decisionAt) $timeline[] = ['label' => 'Decision Released', 'ts' => $decisionAt, 'note' => ucfirst($viewingApp['status'])];

      } catch (Exception $e) {
        $timeline = [];
      }
    }
}
$stmt = $pdo->prepare('SELECT a.*, s.title as scholarship_title, s.organization, s.status as scholarship_status 
                       FROM applications a 
                       LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                       WHERE a.user_id = :uid 
                       ORDER BY a.created_at DESC');
$stmt->execute([':uid' => $user_id]);
$apps = $stmt->fetchAll();
?>
<?php
$page_title = 'My Applications - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📝 My Applications</h1>
</div>
<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
        
        <?php if ($viewingApp): ?>
          <a href="applications.php" style="color:#2196F3;text-decoration:none;margin-bottom:15px;display:inline-block">← Back to Applications</a>
          <div style="margin-top:20px;background:#f9f9f9;padding:20px;border-radius:8px">
            <h2><?= htmlspecialchars($viewingApp['scholarship_title']) ?></h2>
            <p style="color:#666;margin-bottom:20px"><?= htmlspecialchars($viewingApp['scholarship_desc'] ?? '') ?></p>
            
            <div style="margin-bottom:20px">
              <strong>Status:</strong> 
              <?php
                $status = $viewingApp['status'];
                $s = strtolower($status);
                $status_color = ['draft'=>'#999','submitted'=>'#2196F3','pending'=>'#FF9800','under_review'=>'#2196F3','approved'=>'#4CAF50','rejected'=>'#f44336','waitlisted'=>'#FFC107'];
                $color = $status_color[$s] ?? '#999';
              ?>
              <span style="color:<?= $color ?>;font-weight:bold"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($status))) ?></span>
            </div>
            
            <div style="margin-bottom:20px">
              <strong>Submitted:</strong> <?= htmlspecialchars($viewingApp['created_at']) ?>
            </div>

            <?php
              // Show rejection reason from latest notification
              if (strtolower($viewingApp['status']) === 'rejected') {
                try {
                  $rejStmt = $pdo->prepare("SELECT message FROM notifications WHERE user_id = :uid AND related_application_id = :aid AND title LIKE '%Rejected%' ORDER BY created_at DESC LIMIT 1");
                  $rejStmt->execute([':uid' => $user_id, ':aid' => $viewingApp['id']]);
                  $rejMsg = $rejStmt->fetchColumn();
                } catch (Exception $e) { $rejMsg = null; }
                if ($rejMsg): ?>
                  <div style="background:#fff5f5;border-left:4px solid #f44336;padding:16px;border-radius:8px;margin-bottom:20px;">
                    <strong style="color:#c62828;">Rejection Notice:</strong>
                    <p style="margin:8px 0 0;color:#555;"><?= htmlspecialchars($rejMsg) ?></p>
                  </div>
                <?php endif;
              }
              if (strtolower($viewingApp['status']) === 'waitlisted'): ?>
                <div style="background:#fffde7;border-left:4px solid #FFC107;padding:16px;border-radius:8px;margin-bottom:20px;">
                  <strong style="color:#e65100;">You are on the waitlist.</strong>
                  <p style="margin:8px 0 0;color:#555;">You may be promoted if a scholarship slot becomes available. No action needed — we'll notify you.</p>
                </div>
              <?php endif; ?>

            <?php if (in_array(strtolower($viewingApp['status']), ['rejected', 'waitlisted'])): ?>
              <div style="background:#f0f4ff;border-left:4px solid #2196F3;padding:16px;border-radius:8px;margin-bottom:20px;">
                <strong style="color:#1565c0;">Have a question or want to appeal?</strong>
                <p style="margin:8px 0 8px;color:#555;font-size:0.9rem;">You can submit feedback about this decision through the Feedback page.</p>
                <a href="feedback.php" class="btn btn-ghost btn-sm">Submit Feedback / Appeal</a>
              </div>
            <?php endif; ?>
            
            <hr style="margin:20px 0">
            <h4>Application Details</h4>
            <?php if ($viewingApp['motivational_letter']): ?>
              <?php $formData = json_decode($viewingApp['motivational_letter'], true); ?>
              <?php if ($formData): ?>
                <table style="width:100%;border-collapse:collapse">
                  <?php foreach ($formData as $key => $value): ?>
                    <?php if (is_string($value) || is_numeric($value)): ?>
                      <tr style="border-bottom:1px solid #eee">
                        <td style="padding:10px;font-weight:bold;width:30%"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?></td>
                        <td style="padding:10px"><?= htmlspecialchars($value) ?></td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </table>
              <?php endif; ?>
            <?php endif; ?>
            
                <?php if (!empty($timeline)): ?>
                  <div style="margin-top:20px" class="panel">
                    <h4>Application Timeline</h4>
                    <ul style="list-style:none;padding:0;margin:0">
                      <?php foreach ($timeline as $t): ?>
                        <li style="padding:10px 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
                          <div style="display:flex;flex-direction:column">
                            <strong><?= htmlspecialchars($t['label']) ?></strong>
                            <?php if (!empty($t['note'])): ?><small style="color:#666;margin-top:4px"><?= htmlspecialchars($t['note']) ?></small><?php endif; ?>
                          </div>
                          <div style="color:#666;min-width:180px;text-align:right">
                            <?php if (!empty($t['ts'])): ?>
                              <?= htmlspecialchars(date('M d, Y H:i', strtotime($t['ts']))) ?>
                            <?php else: ?>
                              <small class="muted">—</small>
                            <?php endif; ?>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>

                <?php
                  // Fetch documents for this application for student view
                  $studentDocs = [];
                  try {
                    $dstmt = $pdo->prepare('SELECT id, document_type, file_name, file_path, verification_status, verified_at, notes, uploaded_at FROM documents WHERE application_id = :aid');
                    $dstmt->execute([':aid' => $viewingApp['id']]);
                    $studentDocs = $dstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                  } catch (Exception $e) { $studentDocs = []; }
                ?>

                <div style="margin-top:20px">
                  <h4>Submitted Documents</h4>
                  <?php if (empty($studentDocs)): ?>
                    <p class="muted">No documents uploaded for this application.</p>
                  <?php else: ?>
                    <table style="width:100%;border-collapse:collapse">
                      <thead><tr><th>File</th><th>Type</th><th>Status</th><th>Verified At</th><th>Notes</th></tr></thead>
                      <tbody>
                        <?php foreach ($studentDocs as $doc): ?>
                          <tr style="border-bottom:1px solid #eee">
                            <td style="padding:10px"><a href="document_view.php?id=<?= (int)$doc['id'] ?>" target="_blank"><?= htmlspecialchars($doc['file_name']) ?></a></td>
                            <td style="padding:10px"><?= htmlspecialchars($doc['document_type']) ?></td>
                            <td style="padding:10px;text-transform:capitalize"><?= htmlspecialchars($doc['verification_status']) ?></td>
                            <td style="padding:10px"><?= !empty($doc['verified_at']) ? htmlspecialchars($doc['verified_at']) : '—' ?></td>
                            <td style="padding:10px"><?= !empty($doc['notes']) ? htmlspecialchars($doc['notes']) : '—' ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  <?php endif; ?>
                </div>
              </div>
        <?php else: ?>
          <?php if (empty($apps)): ?>
          <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <h3 class="empty-state-title">No Applications Yet</h3>
            <p class="empty-state-description">You haven't submitted any scholarship applications. Start your journey today!</p>
            <a href="apply_scholarship.php" class="btn btn-primary">Apply for Scholarship</a>
          </div>
        <?php else: ?>
          <table class="modern-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Scholarship</th>
                <th>Title</th>
                <th>Status</th>
                <th>Document</th>
                <th>Submitted</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($apps as $a): ?>
                <tr style="border-top:1px solid #eee">
                  <td><?= htmlspecialchars($a['id']) ?></td>
                  <td>
                    <?php if ($a['scholarship_title']): ?>
                      <strong><?= htmlspecialchars($a['scholarship_title']) ?></strong><br>
                      <small><?= htmlspecialchars($a['organization'] ?? 'N/A') ?></small>
                    <?php else: ?>
                      <em>General Application</em>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                      $appTitle = 'Application';
                      if ($a['motivational_letter']) {
                        $formData = json_decode($a['motivational_letter'], true);
                        if ($formData && isset($formData['full_name'])) {
                          $appTitle = htmlspecialchars($formData['full_name']);
                        }
                      }
                      echo $appTitle;
                    ?>
                  </td>
                  <td>
                    <?php
                      $status = $a['status'];
                      $s = strtolower($status);
                      $status_color = [
                        'draft' => '#999',
                        'submitted' => '#2196F3',
                        'pending' => '#FF9800',
                        'under_review' => '#2196F3',
                        'approved' => '#4CAF50',
                        'rejected' => '#f44336',
                        'waitlisted' => '#FFC107'
                      ];
                      $color = $status_color[$s] ?? '#999';
                    ?>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '_', $status)) ?>">
                      <?= ucfirst(str_replace('_', ' ', htmlspecialchars($status))) ?>
                    </span>
                  </td>
                  <td><?php if (!empty($a['document'])): ?><a href="../<?= htmlspecialchars($a['document']) ?>" target="_blank">View</a><?php else: ?>—<?php endif; ?></td>
                  <td><small><?= htmlspecialchars($a['created_at']) ?></small></td>
                  <td>
                    <a href="applications.php?view=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                    <?php if ($a['status'] === 'draft'): ?>
                      <a href="apply_scholarship.php?scholarship_id=<?= (int)$a['scholarship_id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Delete this draft?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete_draft">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-ghost btn-sm" style="color:var(--peach)">Delete</button>
                      </form>
                    <?php elseif (in_array($a['status'], ['submitted','pending','under_review'])): ?>
                      <form method="POST" style="display:inline" onsubmit="return confirm('Withdraw this application?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="withdraw">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-ghost btn-sm" style="color:var(--peach)">Withdraw</button>
                      </form>
                    <?php elseif ($a['status'] === 'approved'): ?>
                      <a href="award_letter.php?application_id=<?= (int)$a['id'] ?>" class="btn btn-primary btn-sm" target="_blank">📄 Award Letter</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <?php endif; ?>
      </div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>