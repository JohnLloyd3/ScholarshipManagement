<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please log in.';
    header('Location: ../auth/login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Handle deletion by owner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_application') {
  // CSRF protection
  if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request (CSRF token missing or incorrect).';
    header('Location: applications.php');
    exit;
  }
  $delId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  if ($delId > 0) {
    try {
      // Prevent students from deleting via crafted POST requests.
      $role = $_SESSION['user']['role'] ?? 'student';
      if ($role === 'student') {
        $_SESSION['flash'] = 'You are not authorized to perform this action.';
        header('Location: applications.php');
        exit;
      }
      $stmt = $pdo->prepare('SELECT id, user_id FROM applications WHERE id = :id LIMIT 1');
      $stmt->execute([':id' => $delId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        $_SESSION['flash'] = 'Application not found.';
      } else {
        // Allow delete (admins/staff) — perform delete
        $pdo->prepare('DELETE FROM applications WHERE id = :id')->execute([':id' => $delId]);
        $_SESSION['success'] = 'Application deleted successfully.';
      }
    } catch (Exception $e) {
      $_SESSION['flash'] = 'Failed to delete application.';
    }
  } else {
    $_SESSION['flash'] = 'Invalid application ID.';
  }
  header('Location: applications.php');
  exit;
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
  <p class="text-muted">Track and manage your scholarship applications</p>
</div>
<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
        
        <?php if ($viewingApp): ?>
          <a href=\"applications.php\" style=\"color:#2196F3;text-decoration:none;margin-bottom:15px;display:inline-block\">← Back to Applications</a>
          <div style=\"margin-top:20px;background:#f9f9f9;padding:20px;border-radius:8px\">
            <h2><?= htmlspecialchars($viewingApp['scholarship_title']) ?></h2>
            <p style=\"color:#666;margin-bottom:20px\"><?= htmlspecialchars($viewingApp['scholarship_desc'] ?? '') ?></p>
            
            <div style=\"margin-bottom:20px\">
              <strong>Status:</strong> 
              <?php
                $status = $viewingApp['status'];
                $s = strtolower($status);
                $status_color = ['draft'=>'#999','submitted'=>'#2196F3','pending'=>'#FF9800','under_review'=>'#2196F3','approved'=>'#4CAF50','rejected'=>'#f44336','waitlisted'=>'#FFC107'];
                $color = $status_color[$s] ?? '#999';
              ?>
              <span style=\"color:<?= $color ?>;font-weight:bold\"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($status))) ?></span>
            </div>
            
            <div style=\"margin-bottom:20px\">
              <strong>Submitted:</strong> <?= htmlspecialchars($viewingApp['created_at']) ?>
            </div>
            
            <hr style=\"margin:20px 0\">
            <h4>Application Details</h4>
            <?php if ($viewingApp['motivational_letter']): ?>
              <?php $formData = json_decode($viewingApp['motivational_letter'], true); ?>
              <?php if ($formData): ?>
                <table style=\"width:100%;border-collapse:collapse\">
                  <?php foreach ($formData as $key => $value): ?>
                    <?php if (is_string($value) || is_numeric($value)): ?>
                      <tr style=\"border-bottom:1px solid #eee\">
                        <td style=\"padding:10px;font-weight:bold;width:30%\"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($key))) ?></td>
                        <td style=\"padding:10px\"><?= htmlspecialchars($value) ?></td>
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
                    <a href="applications.php?view=<?= $a['id'] ?>" style="color:#2196F3;text-decoration:none;margin-right:8px">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        <?php endif; ?>
      </section>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>