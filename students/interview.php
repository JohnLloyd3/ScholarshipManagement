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
        ia.notes,
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

<style>
.interview-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 1400px) {
  .interview-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 1024px) {
  .interview-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 640px) {
  .interview-grid { grid-template-columns: 1fr; }
}

.interview-box {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  padding: 1.5rem;
  transition: all 0.3s ease;
  cursor: pointer;
  border: 2px solid transparent;
  position: relative;
  overflow: hidden;
}

.interview-box:hover {
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  transform: translateY(-2px);
  border-color: var(--primary-color);
}

.interview-box.completed {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-color: #10b981;
}

.interview-box.in-progress {
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border-color: #3b82f6;
}

.interview-box-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.interview-group-code {
  font-size: 3rem;
  font-weight: 800;
  line-height: 1;
  color: var(--primary-color);
  text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.interview-status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.interview-status-badge.completed {
  background: #10b981;
  color: white;
}

.interview-status-badge.in-progress {
  background: #3b82f6;
  color: white;
}

.interview-box-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 0.5rem 0;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.interview-box-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(0,0,0,0.1);
}

.interview-info-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #4b5563;
}

.interview-info-item i {
  width: 16px;
  text-align: center;
  color: var(--primary-color);
}

.interview-progress-mini {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}

.progress-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #d1d5db;
  transition: all 0.3s ease;
}

.progress-dot.done {
  background: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.progress-dot.pending {
  background: #fbbf24;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.interview-expand-icon {
  position: absolute;
  bottom: 1rem;
  right: 1rem;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255,255,255,0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

.interview-box:hover .interview-expand-icon {
  background: var(--primary-color);
  color: white;
  transform: scale(1.1);
}
</style>

<?php if (!empty($assignments)): ?>
<div class="interview-grid">
  <?php foreach($assignments as $idx => $assignment): ?>
    <?php 
      $isCompleted = ($assignment['final_status'] === 'completed');
      $cardId = 'interview-card-' . $idx;
    ?>
    <div 
      class="interview-box <?= $isCompleted ? 'completed' : 'in-progress' ?>" 
      onclick="toggleInterviewCard('<?= $cardId ?>')"
    >
      <div class="interview-box-header">
        <div class="interview-group-code"><?= htmlspecialchars($assignment['group_code']) ?></div>
        <span class="interview-status-badge <?= $isCompleted ? 'completed' : 'in-progress' ?>">
          <?= $isCompleted ? '✓ Done' : '⏳ Pending' ?>
        </span>
      </div>
      
      <h3 class="interview-box-title"><?= htmlspecialchars($assignment['scholarship_title']) ?></h3>
      
      <div class="interview-box-info">
        <div class="interview-info-item">
          <i class="fas fa-calendar"></i>
          <span><?= date('M d, Y', strtotime($assignment['session_date'])) ?></span>
        </div>
        <div class="interview-info-item">
          <i class="fas fa-clock"></i>
          <span><?= $assignment['time_block'] === 'AM' ? 'Morning' : 'Afternoon' ?> (<?= date('g:i A', strtotime($assignment['time_start'])) ?>)</span>
        </div>
        <div class="interview-info-item">
          <i class="fas fa-users"></i>
          <span><?= (int)$assignment['current_count'] ?>/<?= (int)$assignment['max_capacity'] ?> applicants</span>
        </div>
      </div>
      
      <div class="interview-progress-mini" title="Attendance • Interview • Final">
        <div class="progress-dot <?= $assignment['attendance_status'] === 'present' ? 'done' : 'pending' ?>"></div>
        <div class="progress-dot <?= $assignment['interview_status'] === 'done' ? 'done' : 'pending' ?>"></div>
        <div class="progress-dot <?= $assignment['final_status'] === 'completed' ? 'done' : 'pending' ?>"></div>
      </div>
      
      <div class="interview-expand-icon">
        <i class="fas fa-chevron-right"></i>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Detailed View Modal -->
<?php foreach($assignments as $idx => $assignment): ?>
  <?php 
    $isCompleted = ($assignment['final_status'] === 'completed');
    $cardId = 'interview-card-' . $idx;
  ?>
  <div id="<?= $cardId ?>" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 900px;">
      <div class="modal-header">
        <h2><?= htmlspecialchars($assignment['scholarship_title']) ?></h2>
        <button class="modal-close" onclick="toggleInterviewCard('<?= $cardId ?>')">&times;</button>
      </div>
      
      <div style="padding: 1.5rem;">
        <!-- Header Info -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px;">
          <div>
            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Your Interview Group</div>
            <div style="font-size: 3rem; font-weight: 800; line-height: 1;"><?= htmlspecialchars($assignment['group_code']) ?></div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Status</div>
            <span class="status-badge" style="background: <?= $isCompleted ? '#10b981' : '#3b82f6' ?>; color: white; font-size: 1rem; padding: 0.5rem 1rem;">
              <?= $isCompleted ? '✓ Completed' : '⏳ In Progress' ?>
            </span>
          </div>
        </div>
        
        <!-- Session Details -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
          <h3 style="margin: 0 0 1rem 0; color: #1f2937;"><i class="fas fa-calendar-alt"></i> Session Details</h3>
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
            <div>
              <div class="text-muted" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Date</div>
              <div style="font-weight: 600;"><?= date('F d, Y', strtotime($assignment['session_date'])) ?></div>
            </div>
            <div>
              <div class="text-muted" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Time</div>
              <div style="font-weight: 600;"><?= $assignment['time_block'] === 'AM' ? 'Morning' : 'Afternoon' ?> (<?= date('g:i A', strtotime($assignment['time_start'])) ?> - <?= date('g:i A', strtotime($assignment['time_end'])) ?>)</div>
            </div>
            <div>
              <div class="text-muted" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Group Size</div>
              <div style="font-weight: 600;"><?= (int)$assignment['current_count'] ?> / <?= (int)$assignment['max_capacity'] ?> applicants</div>
            </div>
            <div>
              <div class="text-muted" style="font-size: 0.875rem; margin-bottom: 0.25rem;">Assigned On</div>
              <div style="font-weight: 600;"><?= date('M d, Y', strtotime($assignment['assigned_at'])) ?></div>
            </div>
          </div>
        </div>
        
        <!-- Progress Tracker -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem;">
          <h3 style="color: white; margin: 0 0 1rem 0;"><i class="fas fa-tasks"></i> Interview Progress</h3>
          <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; text-align: center;">
              <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                <?= $assignment['attendance_status'] === 'present' ? '✓' : ($assignment['attendance_status'] === 'absent' ? '✗' : '⏳') ?>
              </div>
              <div style="font-weight: 600; margin-bottom: 0.25rem;">Attendance</div>
              <div style="opacity: 0.9; font-size: 0.875rem;"><?= ucfirst($assignment['attendance_status']) ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; text-align: center;">
              <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                <?= $assignment['interview_status'] === 'done' ? '✓' : '⏳' ?>
              </div>
              <div style="font-weight: 600; margin-bottom: 0.25rem;">Interview</div>
              <div style="opacity: 0.9; font-size: 0.875rem;"><?= $assignment['interview_status'] === 'done' ? 'Done' : 'Pending' ?></div>
            </div>
            <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; text-align: center;">
              <div style="font-size: 2rem; margin-bottom: 0.5rem;">
                <?= $assignment['final_status'] === 'completed' ? '✓' : '⏳' ?>
              </div>
              <div style="font-weight: 600; margin-bottom: 0.25rem;">Final Status</div>
              <div style="opacity: 0.9; font-size: 0.875rem;"><?= ucfirst($assignment['final_status']) ?></div>
            </div>
          </div>
        </div>
        
        <!-- Important Info -->
        <?php if (!$isCompleted): ?>
        <div style="padding: 1.5rem; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px;">
          <h4 style="margin: 0 0 1rem 0; color: #92400e;"><i class="fas fa-exclamation-triangle"></i> Important Reminders</h4>
          <ul style="margin: 0; padding-left: 1.5rem; color: #78350f;">
            <li>Arrive <strong>15 minutes early</strong></li>
            <li>Bring <strong>valid ID</strong> and documents</li>
            <li>Dress appropriately</li>
            <li>Assignment is <strong>locked</strong></li>
          </ul>
        </div>
        <?php else: ?>
        <div style="padding: 1.5rem; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 8px;">
          <h4 style="margin: 0 0 0.5rem 0; color: #065f46;"><i class="fas fa-check-circle"></i> Interview Completed</h4>
          <p style="margin: 0; color: #047857;">You have successfully completed this interview. Your disbursement is being processed.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<script>
function toggleInterviewCard(cardId) {
  const modal = document.getElementById(cardId);
  if (modal.style.display === 'none' || modal.style.display === '') {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  } else {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
  if (event.target.classList.contains('modal')) {
    event.target.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
});
</script>

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
