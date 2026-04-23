<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

$pdo = getPDO();
$userId = $_SESSION['user_id'];

// Get student's interview assignments
$stmt = $pdo->prepare('
    SELECT 
        ia.id as assignment_id,
        ia.attendance_status,
        ia.orientation_status,
        ia.interview_status,
        ia.final_status,
        ia.assigned_at,
        ia.locked,
        g.group_code,
        g.max_capacity,
        g.current_count,
        s.session_date,
        s.time_block,
        s.time_start,
        s.time_end,
        s.status as session_status,
        sch.id as scholarship_id,
        sch.title as scholarship_title,
        a.id as application_id
    FROM interview_assignments ia
    JOIN interview_groups g ON ia.group_id = g.id
    JOIN interview_sessions s ON g.session_id = s.id
    JOIN applications a ON ia.application_id = a.id
    JOIN scholarships sch ON a.scholarship_id = sch.id
    WHERE a.user_id = :uid
    ORDER BY s.session_date ASC, s.time_block ASC
');
$stmt->execute([':uid' => $userId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Interviews - ScholarHub';
$csrf_token = generateCSRFToken();
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-calendar-check"></i> My Interview Schedule</h1>
  <p class="text-muted">View your assigned interview groups and track your progress</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<?php if (!empty($assignments)): ?>
  <?php foreach($assignments as $assignment): ?>
    <div class="content-card" style="margin-bottom: var(--space-xl);">
      <!-- Header -->
      <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-lg); padding-bottom: var(--space-lg); border-bottom: 2px solid #e5e7eb;">
        <div>
          <h2 style="margin: 0 0 var(--space-sm) 0; color: var(--primary-color);">
            <?= htmlspecialchars($assignment['scholarship_title']) ?>
          </h2>
          <p class="text-muted" style="margin: 0;">
            <i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($assignment['session_date'])) ?> | 
            <i class="fas fa-clock"></i> <?= $assignment['time_block'] === 'AM' ? 'Morning Session' : 'Afternoon Session' ?>
            (<?= date('g:i A', strtotime($assignment['time_start'])) ?> - <?= date('g:i A', strtotime($assignment['time_end'])) ?>)
          </p>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary-color);">
            <?= htmlspecialchars($assignment['group_code']) ?>
          </div>
          <div class="text-muted" style="font-size: 0.9rem;">Your Group</div>
        </div>
      </div>
      
      <!-- Important Notice -->
      <?php if ($assignment['locked']): ?>
        <div class="alert alert-warning" style="margin-bottom: var(--space-lg);">
          <i class="fas fa-lock"></i> <strong>Assignment Locked:</strong> Your interview group assignment is final and cannot be changed. Please arrive on time for your scheduled session.
        </div>
      <?php endif; ?>
      
      <!-- Progress Tracker -->
      <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: var(--space-xl); border-radius: var(--r-lg); margin-bottom: var(--space-lg);">
        <h3 style="color: white; margin: 0 0 var(--space-lg) 0;">
          <i class="fas fa-tasks"></i> Interview Progress
        </h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg);">
          <!-- Attendance -->
          <div style="background: rgba(255,255,255,0.1); padding: var(--space-lg); border-radius: var(--r-md); backdrop-filter: blur(10px);">
            <div style="font-size: 2rem; margin-bottom: var(--space-sm);">
              <?php if ($assignment['attendance_status'] === 'present'): ?>
                ✓
              <?php elseif ($assignment['attendance_status'] === 'absent'): ?>
                ✗
              <?php else: ?>
                ⏳
              <?php endif; ?>
            </div>
            <div style="font-weight: 600; margin-bottom: var(--space-xs);">Attendance</div>
            <div style="opacity: 0.9; font-size: 0.9rem;">
              <?= ucfirst($assignment['attendance_status']) ?>
            </div>
          </div>
          
          <!-- Orientation -->
          <div style="background: rgba(255,255,255,0.1); padding: var(--space-lg); border-radius: var(--r-md); backdrop-filter: blur(10px);">
            <div style="font-size: 2rem; margin-bottom: var(--space-sm);">
              <?php if ($assignment['orientation_status'] === 'done'): ?>
                ✓
              <?php else: ?>
                ⏳
              <?php endif; ?>
            </div>
            <div style="font-weight: 600; margin-bottom: var(--space-xs);">Orientation</div>
            <div style="opacity: 0.9; font-size: 0.9rem;">
              <?= ucfirst($assignment['orientation_status']) ?>
            </div>
          </div>
          
          <!-- Individual Interview -->
          <div style="background: rgba(255,255,255,0.1); padding: var(--space-lg); border-radius: var(--r-md); backdrop-filter: blur(10px);">
            <div style="font-size: 2rem; margin-bottom: var(--space-sm);">
              <?php if ($assignment['interview_status'] === 'done'): ?>
                ✓
              <?php else: ?>
                ⏳
              <?php endif; ?>
            </div>
            <div style="font-weight: 600; margin-bottom: var(--space-xs);">Individual Interview</div>
            <div style="opacity: 0.9; font-size: 0.9rem;">
              <?= ucfirst($assignment['interview_status']) ?>
            </div>
          </div>
          
          <!-- Final Status -->
          <div style="background: rgba(255,255,255,0.1); padding: var(--space-lg); border-radius: var(--r-md); backdrop-filter: blur(10px);">
            <div style="font-size: 2rem; margin-bottom: var(--space-sm);">
              <?php if ($assignment['final_status'] === 'completed'): ?>
                ✓
              <?php else: ?>
                ⏳
              <?php endif; ?>
            </div>
            <div style="font-weight: 600; margin-bottom: var(--space-xs);">Final Status</div>
            <div style="opacity: 0.9; font-size: 0.9rem;">
              <?= ucfirst($assignment['final_status']) ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Group Information -->
      <div style="background: #f8f9fa; padding: var(--space-lg); border-radius: var(--r-md);">
        <h4 style="margin: 0 0 var(--space-md) 0;">
          <i class="fas fa-info-circle"></i> Group Information
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md);">
          <div>
            <div class="text-muted" style="font-size: 0.9rem;">Group Code</div>
            <div style="font-size: 1.2rem; font-weight: 600;"><?= htmlspecialchars($assignment['group_code']) ?></div>
          </div>
          <div>
            <div class="text-muted" style="font-size: 0.9rem;">Group Size</div>
            <div style="font-size: 1.2rem; font-weight: 600;"><?= (int)$assignment['current_count'] ?> / <?= (int)$assignment['max_capacity'] ?> applicants</div>
          </div>
          <div>
            <div class="text-muted" style="font-size: 0.9rem;">Session Status</div>
            <div>
              <span class="status-badge status-<?= $assignment['session_status'] ?>">
                <?= ucfirst($assignment['session_status']) ?>
              </span>
            </div>
          </div>
          <div>
            <div class="text-muted" style="font-size: 0.9rem;">Assigned On</div>
            <div style="font-size: 1.2rem; font-weight: 600;"><?= date('M d, Y', strtotime($assignment['assigned_at'])) ?></div>
          </div>
        </div>
      </div>
      
      <!-- Important Reminders -->
      <div style="margin-top: var(--space-lg); padding: var(--space-lg); background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: var(--r-md);">
        <h4 style="margin: 0 0 var(--space-md) 0; color: #92400e;">
          <i class="fas fa-exclamation-triangle"></i> Important Reminders
        </h4>
        <ul style="margin: 0; padding-left: var(--space-lg); color: #78350f;">
          <li>Please arrive <strong>15 minutes before</strong> your scheduled time</li>
          <li>Bring a <strong>valid ID</strong> and all required documents</li>
          <li>Dress appropriately for the interview</li>
          <li>Your group assignment is <strong>locked</strong> and cannot be changed</li>
          <li>Contact the scholarship office if you have any concerns</li>
        </ul>
      </div>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="content-card">
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-calendar-alt"></i></div>
      <h3 class="empty-state-title">No Interview Scheduled</h3>
      <p class="empty-state-description">
        You don't have any interview assignments yet. Once your application is approved and an interview is scheduled, it will appear here.
      </p>
      <a href="applications.php" class="btn btn-primary">
        <i class="fas fa-clipboard-list"></i> View My Applications
      </a>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
