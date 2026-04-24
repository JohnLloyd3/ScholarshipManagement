<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/InterviewHelper.php';
startSecureSession();

requireLogin();
requireAnyRole(['admin', 'staff'], 'Admin or Staff access required');

$pdo = getPDO();
$interviewHelper = new InterviewHelper($pdo);

$groupId = (int)($_GET['group_id'] ?? 0);

if (!$groupId) {
    $_SESSION['flash'] = 'Invalid group ID.';
    header('Location: interview_management.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: interview_group_view.php?group_id=' . $groupId);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_progress') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $updates = [];
        
        if (isset($_POST['attendance_status'])) {
            $updates['attendance_status'] = $_POST['attendance_status'];
        }
        if (isset($_POST['orientation_status'])) {
            $updates['orientation_status'] = $_POST['orientation_status'];
        }
        if (isset($_POST['interview_status'])) {
            $updates['interview_status'] = $_POST['interview_status'];
        }
        if (isset($_POST['final_status'])) {
            $updates['final_status'] = $_POST['final_status'];
        }
        if (isset($_POST['notes'])) {
            $updates['notes'] = trim($_POST['notes']);
        }
        
        if ($assignmentId && !empty($updates)) {
            if ($interviewHelper->updateApplicantProgress($assignmentId, $updates)) {
                $_SESSION['success'] = 'Progress updated successfully!';
            } else {
                $_SESSION['flash'] = 'Failed to update progress.';
            }
        }
    }
    
    header('Location: interview_group_view.php?group_id=' . $groupId);
    exit;
}

// Get group details
$groupStmt = $pdo->prepare('
    SELECT 
        g.*,
        s.session_date,
        s.session_period as time_block,
        s.start_time as time_start,
        s.end_time as time_end
    FROM interview_groups g
    JOIN interview_sessions s ON g.session_id = s.id
    WHERE g.id = :gid
');
$groupStmt->execute([':gid' => $groupId]);
$group = $groupStmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    $_SESSION['flash'] = 'Group not found.';
    header('Location: interview_management.php');
    exit;
}

// Get scholarship info from the first applicant in this group
$scholarshipStmt = $pdo->prepare('
    SELECT DISTINCT sch.id as scholarship_id, sch.title as scholarship_title
    FROM interview_assignments ia
    JOIN applications a ON ia.application_id = a.id
    JOIN scholarships sch ON a.scholarship_id = sch.id
    WHERE ia.group_id = :gid
    LIMIT 1
');
$scholarshipStmt->execute([':gid' => $groupId]);
$scholarshipInfo = $scholarshipStmt->fetch(PDO::FETCH_ASSOC);

if ($scholarshipInfo) {
    $group['scholarship_id'] = $scholarshipInfo['scholarship_id'];
    $group['scholarship_title'] = $scholarshipInfo['scholarship_title'];
} else {
    $group['scholarship_id'] = null;
    $group['scholarship_title'] = 'N/A';
}

if (!$group) {
    $_SESSION['flash'] = 'Group not found.';
    header('Location: interview_management.php');
    exit;
}

// Get applicants in this group
$applicants = $interviewHelper->getGroupApplicants($groupId);

$page_title = 'Group ' . $group['group_code'] . ' - Interview Management';
$csrf_token = generateCSRFToken();
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <div style="display: flex; justify-content: space-between; align-items: center;">
    <div>
      <h1><i class="fas fa-users"></i> Group <?= htmlspecialchars($group['group_code']) ?></h1>
      <p class="text-muted" style="margin: var(--space-sm) 0 0 0;">
        <?= htmlspecialchars($group['scholarship_title']) ?> | 
        <?= date('F d, Y', strtotime($group['session_date'])) ?> | 
        <?= $group['time_block'] === 'AM' ? 'Morning' : 'Afternoon' ?> Session
        (<?= date('g:i A', strtotime($group['time_start'])) ?> - <?= date('g:i A', strtotime($group['time_end'])) ?>)
      </p>
    </div>
    <a href="interview_management.php?scholarship_id=<?= (int)$group['scholarship_id'] ?>" class="btn btn-ghost">
      <i class="fas fa-arrow-left"></i> Back to Schedule
    </a>
  </div>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Group Summary -->
<div class="content-card">
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-lg);">
    <div>
      <div class="text-muted" style="font-size: 0.9rem;">Total Applicants</div>
      <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
        <?= count($applicants) ?> / <?= (int)$group['capacity'] ?>
      </div>
    </div>
    <div>
      <div class="text-muted" style="font-size: 0.9rem;">Present</div>
      <div style="font-size: 2rem; font-weight: 700; color: #10b981;">
        <?= count(array_filter($applicants, fn($a) => $a['attendance_status'] === 'present')) ?>
      </div>
    </div>
    <div>
      <div class="text-muted" style="font-size: 0.9rem;">Interview Done</div>
      <div style="font-size: 2rem; font-weight: 700; color: #8b5cf6;">
        <?= count(array_filter($applicants, fn($a) => $a['interview_status'] === 'done')) ?>
      </div>
    </div>
    <div>
      <div class="text-muted" style="font-size: 0.9rem;">Completed</div>
      <div style="font-size: 2rem; font-weight: 700; color: #059669;">
        <?= count(array_filter($applicants, fn($a) => $a['final_status'] === 'completed')) ?>
      </div>
    </div>
  </div>
</div>

<!-- Applicants List -->
<div class="content-card" style="margin-top: var(--space-xl);">
  <h2><i class="fas fa-clipboard-list"></i> Applicants</h2>
  
  <?php if (!empty($applicants)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Name</th>
          <th>Attendance</th>
          <th>Interview</th>
          <th>Final Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($applicants as $applicant): ?>
          <tr>
            <td><strong><?= htmlspecialchars($applicant['student_id']) ?></strong></td>
            <td>
              <?= htmlspecialchars($applicant['first_name'] . ' ' . $applicant['last_name']) ?><br>
              <small class="text-muted"><?= htmlspecialchars($applicant['email']) ?></small>
            </td>
            <td>
              <span class="status-badge status-<?= $applicant['attendance_status'] ?>">
                <?= ucfirst($applicant['attendance_status']) ?>
              </span>
            </td>
            <td>
              <span class="status-badge status-<?= $applicant['interview_status'] ?>">
                <?= ucfirst($applicant['interview_status']) ?>
              </span>
            </td>
            <td>
              <span class="status-badge status-<?= $applicant['final_status'] ?>">
                <?= ucfirst($applicant['final_status']) ?>
              </span>
            </td>
            <td>
              <button onclick="openUpdateModal(<?= (int)$applicant['assignment_id'] ?>, <?= htmlspecialchars(json_encode($applicant)) ?>)" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Update
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-users"></i></div>
      <h3 class="empty-state-title">No Applicants Assigned</h3>
      <p class="empty-state-description">This group has no applicants assigned yet.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Update Progress Modal -->
<div id="updateModal" class="modal">
  <div class="modal-content" style="max-width: 600px;">
    <div class="modal-header">
      <h2><i class="fas fa-edit"></i> Update Applicant Progress</h2>
      <span class="modal-close" onclick="document.getElementById('updateModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" id="updateForm">
      <input type="hidden" name="action" value="update_progress">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="assignment_id" id="update_assignment_id">
      
      <div style="padding: var(--space-md); background: #f0f9ff; border-radius: var(--r-md); margin-bottom: var(--space-lg);">
        <strong id="update_applicant_name"></strong><br>
        <small class="text-muted" id="update_applicant_email"></small>
      </div>
      
      <div class="form-group">
        <label>Attendance Status</label>
        <select name="attendance_status" class="form-input" id="update_attendance_status">
          <option value="pending">Pending</option>
          <option value="present">Present</option>
          <option value="absent">Absent</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Interview Status</label>
        <select name="interview_status" class="form-input" id="update_interview_status">
          <option value="pending">Pending</option>
          <option value="done">Done</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Final Status</label>
        <select name="final_status" class="form-input" id="update_final_status">
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-input" id="update_notes" rows="3" placeholder="Add any notes or comments..."></textarea>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Progress</button>
      </div>
    </form>
  </div>
</div>

<script>
function openUpdateModal(assignmentId, applicant) {
  document.getElementById('update_assignment_id').value = assignmentId;
  document.getElementById('update_applicant_name').textContent = applicant.first_name + ' ' + applicant.last_name;
  document.getElementById('update_applicant_email').textContent = applicant.email;
  document.getElementById('update_attendance_status').value = applicant.attendance_status;
  document.getElementById('update_interview_status').value = applicant.interview_status;
  document.getElementById('update_final_status').value = applicant.final_status;
  document.getElementById('update_notes').value = applicant.notes || '';
  
  document.getElementById('updateModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
