<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/InterviewHelper.php';
startSecureSession();

requireLogin();
requireAnyRole(['admin', 'staff'], 'Admin or Staff access required');

$pdo = getPDO();
$interviewHelper = new InterviewHelper($pdo);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: interview_management.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'auto_assign') {
        $scholarshipId = (int)($_POST['scholarship_id'] ?? 0);
        $sessionDate = trim($_POST['session_date'] ?? '');
        
        if ($scholarshipId && $sessionDate) {
            $result = $interviewHelper->autoAssignApplicants($scholarshipId, $sessionDate);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['flash'] = $result['message'];
            }
        } else {
            $_SESSION['flash'] = 'Please select scholarship and date.';
        }
    }
    
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
    
    header('Location: interview_management.php');
    exit;
}

// Get scholarships with approved applicants who haven't been assigned to interviews yet (for auto-assign)
$scholarshipsForAssign = $pdo->query('
    SELECT s.id, s.title, 
           COUNT(DISTINCT CASE 
               WHEN a.status = "approved" AND ia.id IS NULL THEN a.id 
               ELSE NULL 
           END) as approved_count
    FROM scholarships s
    LEFT JOIN applications a ON s.id = a.scholarship_id
    LEFT JOIN interview_assignments ia ON a.id = ia.application_id
    WHERE s.status = "open"
    GROUP BY s.id
    HAVING approved_count > 0
    ORDER BY s.title ASC
')->fetchAll(PDO::FETCH_ASSOC);

// Get all scholarships with any interview assignments (for viewing schedule)
$scholarshipsWithInterviews = $pdo->query('
    SELECT DISTINCT s.id, s.title,
           COUNT(DISTINCT ia.id) as assignment_count
    FROM scholarships s
    INNER JOIN applications a ON s.id = a.scholarship_id
    INNER JOIN interview_assignments ia ON a.id = ia.application_id
    GROUP BY s.id
    ORDER BY s.title ASC
')->fetchAll(PDO::FETCH_ASSOC);

// Get selected scholarship
$selectedScholarshipId = (int)($_GET['scholarship_id'] ?? 0);
$schedule = [];
if ($selectedScholarshipId) {
    $schedule = $interviewHelper->getInterviewSchedule($selectedScholarshipId);
}

$page_title = 'Interview Management - ScholarHub';
$csrf_token = generateCSRFToken();
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-users"></i> Interview Management System</h1>
  <p class="text-muted">Manage interview sessions, groups, and applicant assignments</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- System Overview -->
<div class="content-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: var(--space-xl);">
  <h2 style="color: white; margin-bottom: var(--space-md);"><i class="fas fa-info-circle"></i> Interview System Overview</h2>
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg);">
    <div>
      <div style="font-size: 0.9rem; opacity: 0.9;">Sessions per Day</div>
      <div style="font-size: 2rem; font-weight: 700;">2</div>
      <div style="font-size: 0.85rem; opacity: 0.8;">Morning & Afternoon</div>
    </div>
    <div>
      <div style="font-size: 0.9rem; opacity: 0.9;">Groups per Session</div>
      <div style="font-size: 2rem; font-weight: 700;">2</div>
      <div style="font-size: 0.85rem; opacity: 0.8;">A1, A2 / B1, B2</div>
    </div>
    <div>
      <div style="font-size: 0.9rem; opacity: 0.9;">Applicants per Group</div>
      <div style="font-size: 2rem; font-weight: 700;">10</div>
      <div style="font-size: 0.85rem; opacity: 0.8;">Maximum capacity</div>
    </div>
    <div>
      <div style="font-size: 0.9rem; opacity: 0.9;">Total per Day</div>
      <div style="font-size: 2rem; font-weight: 700;">40</div>
      <div style="font-size: 0.85rem; opacity: 0.8;">Applicants</div>
    </div>
  </div>
  <div style="margin-top: var(--space-lg); padding-top: var(--space-lg); border-top: 1px solid rgba(255,255,255,0.2);">
    <strong>Time Slots:</strong><br>
    <span style="opacity: 0.9;">Morning (AM): 8:00 AM - 11:30 AM | Afternoon (PM): 1:00 PM - 4:00 PM</span>
  </div>
</div>

<!-- Auto-Assignment and View Schedule Buttons -->
<div style="display:flex;justify-content:flex-end;gap:1rem;margin-bottom:1.5rem;">
  <button class="btn btn-ghost" onclick="document.getElementById('viewScheduleSection').scrollIntoView({behavior:'smooth'})">
    <i class="fas fa-calendar-alt"></i> View Schedule
  </button>
  <button class="btn btn-primary" onclick="document.getElementById('autoAssignModal').style.display='flex'">
    <i class="fas fa-magic"></i> Auto-Assign Applicants
  </button>
</div>

<!-- Auto-Assign Modal -->
<div id="autoAssignModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-content" style="max-width:500px;">
    <div class="modal-header">
      <h3><i class="fas fa-magic"></i> Auto-Assign Applicants</h3>
      <button class="modal-close" onclick="document.getElementById('autoAssignModal').style.display='none'">&times;</button>
    </div>
    <p class="text-muted" style="margin-bottom:1rem;">Automatically assign approved applicants to interview groups</p>
    <form method="POST">
      <input type="hidden" name="action" value="auto_assign">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="form-group">
        <label class="form-label">Select Scholarship *</label>
        <select name="scholarship_id" class="form-input" required>
          <option value="">Choose scholarship...</option>
          <?php foreach($scholarshipsForAssign as $sch): ?>
            <option value="<?= (int)$sch['id'] ?>">
              <?= htmlspecialchars($sch['title']) ?> (<?= (int)$sch['approved_count'] ?> approved)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Interview Date *</label>
        <input type="date" name="session_date" class="form-input" required min="<?= date('Y-m-d') ?>">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('autoAssignModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-magic"></i> Auto-Assign</button>
      </div>
    </form>
  </div>
</div>

<!-- View Schedule -->
<div id="viewScheduleSection" class="content-card" style="margin-top: var(--space-xl);">
  <h2><i class="fas fa-calendar-alt"></i> View Interview Schedule</h2>
  
  <?php if (!empty($scholarshipsWithInterviews)): ?>
  <div class="form-group" style="max-width: 400px;">
    <label>Select Scholarship</label>
    <select class="form-input" onchange="window.location.href='interview_management.php?scholarship_id='+this.value">
      <option value="">Choose scholarship...</option>
      <?php foreach($scholarshipsWithInterviews as $sch): ?>
        <option value="<?= (int)$sch['id'] ?>" <?= $selectedScholarshipId == $sch['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($sch['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  
  <?php if (!empty($schedule)): ?>
    <?php foreach($schedule as $session): ?>
      <div style="margin-top: var(--space-xl); padding: var(--space-lg); background: #f8f9fa; border-radius: var(--r-lg); border-left: 4px solid var(--primary-color);">
        <h3 style="margin: 0 0 var(--space-md) 0;">
          <?= date('F d, Y', strtotime($session['session_date'])) ?> - 
          <?= $session['time_block'] === 'AM' ? 'Morning Session' : 'Afternoon Session' ?>
          <span class="status-badge status-<?= $session['session_status'] ?>" style="margin-left: var(--space-md);">
            <?= ucfirst($session['session_status']) ?>
          </span>
        </h3>
        <p class="text-muted" style="margin: 0 0 var(--space-lg) 0;">
          <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($session['time_start'])) ?> - <?= date('g:i A', strtotime($session['time_end'])) ?>
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-lg);">
          <?php foreach($session['groups'] as $group): ?>
            <div style="background: white; padding: var(--space-lg); border-radius: var(--r-md); box-shadow: var(--shadow-sm);">
              <h4 style="margin: 0 0 var(--space-sm) 0; color: var(--primary-color);">
                Group <?= htmlspecialchars($group['group_code']) ?>
              </h4>
              <p style="margin: 0 0 var(--space-md) 0; color: #666;">
                <strong><?= (int)$group['current_count'] ?></strong> / <?= (int)$group['max_capacity'] ?> applicants
              </p>
              <a href="interview_group_view.php?group_id=<?= (int)$group['group_id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-eye"></i> View Applicants
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php elseif ($selectedScholarshipId): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-calendar-alt"></i></div>
      <h3 class="empty-state-title">No Interview Sessions</h3>
      <p class="empty-state-description">Use the auto-assign form above to create sessions and assign applicants.</p>
    </div>
  <?php endif; ?>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fas fa-users"></i></div>
    <h3 class="empty-state-title">No Approved Applicants</h3>
    <p class="empty-state-description">There are no scholarships with approved applicants yet.</p>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
