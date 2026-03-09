<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

requireLogin();
requireAnyRole(['admin', 'staff'], 'Admin or Staff access required');

$pdo = getPDO();
$user = $_SESSION['user'] ?? [];

// Get pre-fill data from URL (when coming from approved application)
$prefilledScholarshipId = (int)($_GET['scholarship_id'] ?? 0);
$prefilledAppId = (int)($_GET['app_id'] ?? 0);
$prefilledApplicant = null;

if ($prefilledAppId) {
    $stmt = $pdo->prepare('
        SELECT a.id, a.user_id, u.first_name, u.last_name, u.email, s.title as scholarship_title
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN scholarships s ON a.scholarship_id = s.id
        WHERE a.id = :app_id AND a.status = "approved"
    ');
    $stmt->execute([':app_id' => $prefilledAppId]);
    $prefilledApplicant = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_slot') {
        $scholarshipId = (int)($_POST['scholarship_id'] ?? 0);
        $date = trim($_POST['interview_date'] ?? '');
        $time = trim($_POST['interview_time'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 30);
        $location = trim($_POST['location'] ?? '');
        $type = trim($_POST['interview_type'] ?? 'online');
        $meetingLink = trim($_POST['meeting_link'] ?? '');
        $maxApplicants = (int)($_POST['max_applicants'] ?? 1);
        $applicationId = (int)($_POST['application_id'] ?? 0); // Specific applicant
        
        if ($scholarshipId && $date && $time) {
            // Create the interview slot
            $stmt = $pdo->prepare('
                INSERT INTO interview_slots 
                (scholarship_id, interview_date, interview_time, duration_minutes, location, interview_type, meeting_link, max_applicants, created_by)
                VALUES (:sid, :date, :time, :duration, :location, :type, :link, :max, :created_by)
            ');
            $stmt->execute([
                ':sid' => $scholarshipId,
                ':date' => $date,
                ':time' => $time,
                ':duration' => $duration,
                ':location' => $location,
                ':type' => $type,
                ':link' => $meetingLink,
                ':max' => $maxApplicants,
                ':created_by' => $_SESSION['user_id']
            ]);
            
            $slotId = $pdo->lastInsertId();
            
            // If specific application, auto-book them
            if ($applicationId) {
                $appStmt = $pdo->prepare('
                    SELECT a.user_id, a.scholarship_id, u.first_name, u.last_name, u.email, s.title as scholarship_title
                    FROM applications a
                    JOIN users u ON a.user_id = u.id
                    JOIN scholarships s ON a.scholarship_id = s.id
                    WHERE a.id = :id
                ');
                $appStmt->execute([':id' => $applicationId]);
                $userInfo = $appStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userInfo) {
                    // Create booking for this specific applicant
                    $bookStmt = $pdo->prepare('
                        INSERT INTO interview_bookings 
                        (slot_id, application_id, user_id, status, booked_at)
                        VALUES (:slot_id, :app_id, :user_id, "scheduled", NOW())
                    ');
                    $bookStmt->execute([
                        ':slot_id' => $slotId,
                        ':app_id' => $applicationId,
                        ':user_id' => $userInfo['user_id']
                    ]);
                    
                    // Send in-app notification
                    $notifStmt = $pdo->prepare('
                        INSERT INTO notifications 
                        (user_id, title, message, type, related_application_id, created_at)
                        VALUES (:uid, :title, :msg, "application", :app_id, NOW())
                    ');
                    $notifStmt->execute([
                        ':uid' => $userInfo['user_id'],
                        ':title' => 'Interview Scheduled',
                        ':msg' => 'Your interview has been scheduled for ' . date('M d, Y g:i A', strtotime($date . ' ' . $time)),
                        ':app_id' => $applicationId
                    ]);
                    
                    // Send email notification
                    if (!empty($userInfo['email'])) {
                        $emailSubject = 'Interview Scheduled - ' . $userInfo['scholarship_title'];
                        
                        $emailBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
                        $emailBody .= '<div style="background: #c41e3a; color: white; padding: 20px; text-align: center;">';
                        $emailBody .= '<h1 style="margin: 0;">📅 Interview Scheduled</h1>';
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="padding: 30px; background: #f9f9f9;">';
                        $emailBody .= '<p style="font-size: 16px;">Dear ' . htmlspecialchars($userInfo['first_name']) . ',</p>';
                        $emailBody .= '<p style="font-size: 16px;">Your interview has been scheduled for your scholarship application.</p>';
                        
                        $emailBody .= '<div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #c41e3a;">';
                        $emailBody .= '<h3 style="margin-top: 0; color: #c41e3a;">Interview Details</h3>';
                        $emailBody .= '<p style="margin: 10px 0;"><strong>Scholarship:</strong> ' . htmlspecialchars($userInfo['scholarship_title']) . '</p>';
                        $emailBody .= '<p style="margin: 10px 0;"><strong>Date:</strong> ' . date('F d, Y', strtotime($date)) . '</p>';
                        $emailBody .= '<p style="margin: 10px 0;"><strong>Time:</strong> ' . date('g:i A', strtotime($time)) . '</p>';
                        $emailBody .= '<p style="margin: 10px 0;"><strong>Duration:</strong> ' . $duration . ' minutes</p>';
                        $emailBody .= '<p style="margin: 10px 0;"><strong>Type:</strong> ' . ucfirst($type) . '</p>';
                        
                        if ($type === 'online' && $meetingLink) {
                            $emailBody .= '<p style="margin: 10px 0;"><strong>Meeting Link:</strong> <a href="' . htmlspecialchars($meetingLink) . '" style="color: #c41e3a;">' . htmlspecialchars($meetingLink) . '</a></p>';
                        } elseif ($location) {
                            $emailBody .= '<p style="margin: 10px 0;"><strong>Location:</strong> ' . htmlspecialchars($location) . '</p>';
                        }
                        $emailBody .= '</div>';
                        
                        $emailBody .= '<p style="font-size: 14px; color: #666;">Please make sure to attend the interview on time. If you need to reschedule, please contact us as soon as possible.</p>';
                        $emailBody .= '<p style="font-size: 14px; color: #666;">Good luck with your interview!</p>';
                        $emailBody .= '</div>';
                        $emailBody .= '<div style="background: #333; color: white; padding: 15px; text-align: center; font-size: 12px;">';
                        $emailBody .= '<p style="margin: 0;">ScholarHub - Scholarship Management System</p>';
                        $emailBody .= '</div>';
                        $emailBody .= '</div>';
                        
                        // Send email
                        sendEmail($userInfo['email'], $emailSubject, $emailBody, true);
                    }
                    
                    $_SESSION['success'] = 'Interview scheduled successfully! The applicant has been notified via email and in-app notification.';
                    
                    // Redirect back to application view if came from there
                    header('Location: ../staff/application_view.php?id=' . $applicationId);
                    exit;
                }
            } else {
                $_SESSION['success'] = 'Interview slot created successfully!';
            }
        } else {
            $_SESSION['flash'] = 'Please fill all required fields.';
        }
    }
    
    if ($action === 'delete_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        if ($slotId) {
            $stmt = $pdo->prepare('DELETE FROM interview_slots WHERE id = :id');
            $stmt->execute([':id' => $slotId]);
            $_SESSION['success'] = 'Interview slot deleted.';
        }
    }
    
    if ($action === 'update_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $scholarshipId = (int)($_POST['scholarship_id'] ?? 0);
        $date = trim($_POST['interview_date'] ?? '');
        $time = trim($_POST['interview_time'] ?? '');
        $duration = (int)($_POST['duration_minutes'] ?? 30);
        $location = trim($_POST['location'] ?? '');
        $type = trim($_POST['interview_type'] ?? 'online');
        $meetingLink = trim($_POST['meeting_link'] ?? '');
        $maxApplicants = (int)($_POST['max_applicants'] ?? 1);
        
        if ($slotId && $scholarshipId && $date && $time) {
            $stmt = $pdo->prepare('
                UPDATE interview_slots 
                SET scholarship_id = :sid, interview_date = :date, interview_time = :time, 
                    duration_minutes = :duration, location = :location, interview_type = :type, 
                    meeting_link = :link, max_applicants = :max
                WHERE id = :id
            ');
            $stmt->execute([
                ':sid' => $scholarshipId,
                ':date' => $date,
                ':time' => $time,
                ':duration' => $duration,
                ':location' => $location,
                ':type' => $type,
                ':link' => $meetingLink,
                ':max' => $maxApplicants,
                ':id' => $slotId
            ]);
            
            $_SESSION['success'] = 'Interview slot updated successfully!';
        } else {
            $_SESSION['flash'] = 'Please fill all required fields.';
        }
    }
    
    header('Location: interview_slots.php');
    exit;
}

// Get all interview slots with booking counts
$stmt = $pdo->query('
    SELECT 
        s.*,
        sch.title as scholarship_title,
        COUNT(b.id) as bookings_count,
        u.first_name as created_by_name
    FROM interview_slots s
    LEFT JOIN scholarships sch ON s.scholarship_id = sch.id
    LEFT JOIN interview_bookings b ON s.id = b.slot_id AND b.status != "cancelled"
    LEFT JOIN users u ON s.created_by = u.id
    GROUP BY s.id
    ORDER BY s.interview_date DESC, s.interview_time DESC
');
$slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get scholarships for dropdown
$scholarships = $pdo->query('SELECT id, title FROM scholarships WHERE status = "open" ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Interview Slots - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📅 Interview Slots Management</h1>
  <p class="text-muted">Create and manage interview schedules for shortlisted applicants</p>
</div>

<?php if ($prefilledApplicant): ?>
  <div class="alert alert-success" style="background: #e8f5e9; border-left: 4px solid #4CAF50; padding: var(--space-lg); margin-bottom: var(--space-lg);">
    <h4 style="margin: 0 0 var(--space-sm) 0; color: #2e7d32;">✅ Scheduling Interview for Approved Applicant</h4>
    <p style="margin: 0; color: #555;">
      <strong>Applicant:</strong> <?= htmlspecialchars($prefilledApplicant['first_name'] . ' ' . $prefilledApplicant['last_name']) ?> (<?= htmlspecialchars($prefilledApplicant['email']) ?>)<br>
      <strong>Scholarship:</strong> <?= htmlspecialchars($prefilledApplicant['scholarship_title']) ?>
    </p>
  </div>
  <script>
    // Auto-open the modal when coming from approved application
    window.addEventListener('DOMContentLoaded', function() {
      document.getElementById('createModal').style.display = 'block';
    });
  </script>
<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
    <h2>📋 Interview Slots</h2>
    <button onclick="document.getElementById('createModal').style.display='block'" class="btn btn-primary">
      ➕ Create New Slot
    </button>
  </div>

  <?php if (!empty($slots)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Scholarship</th>
          <th>Date & Time</th>
          <th>Type</th>
          <th>Location/Link</th>
          <th>Bookings</th>
          <th>Duration</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($slots as $slot): ?>
          <tr>
            <td><strong><?= htmlspecialchars($slot['scholarship_title'] ?? 'N/A') ?></strong></td>
            <td>
              <?= date('M d, Y', strtotime($slot['interview_date'])) ?><br>
              <small class="text-muted"><?= date('g:i A', strtotime($slot['interview_time'])) ?></small>
            </td>
            <td>
              <span class="status-badge status-<?= $slot['interview_type'] ?>">
                <?= ucfirst($slot['interview_type']) ?>
              </span>
            </td>
            <td>
              <?php if ($slot['interview_type'] === 'online' && $slot['meeting_link']): ?>
                <a href="<?= htmlspecialchars($slot['meeting_link']) ?>" target="_blank" class="text-primary">🔗 Meeting Link</a>
              <?php else: ?>
                <?= htmlspecialchars($slot['location'] ?: 'N/A') ?>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= (int)$slot['bookings_count'] ?></strong> / <?= (int)$slot['max_applicants'] ?>
            </td>
            <td><?= (int)$slot['duration_minutes'] ?> min</td>
            <td>
              <div style="display: flex; gap: 0.5rem;">
                <a href="interview_bookings.php?slot_id=<?= (int)$slot['id'] ?>" class="btn btn-ghost btn-sm" title="View Bookings">👁️ View</a>
                <button onclick="openEditModal(<?= (int)$slot['id'] ?>)" class="btn btn-ghost btn-sm" title="Edit Slot">✏️ Edit</button>
                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Delete this interview slot? All bookings will be removed.')">
                  <input type="hidden" name="action" value="delete_slot">
                  <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Delete Slot" style="color: #dc2626;">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📅</div>
      <h3 class="empty-state-title">No Interview Slots</h3>
      <p class="empty-state-description">Create your first interview slot to get started.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Create Slot Modal -->
<div id="createModal" class="modal">
  <div class="modal-content" style="max-width: 600px;">
    <div class="modal-header">
      <h2>➕ Create Interview Slot</h2>
      <span class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</span>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create_slot">
      <?php if ($prefilledAppId): ?>
        <input type="hidden" name="application_id" value="<?= (int)$prefilledAppId ?>">
      <?php endif; ?>
      
      <?php if ($prefilledApplicant): ?>
        <div style="padding: var(--space-md); background: #e3f2fd; border-radius: var(--radius-md); margin-bottom: var(--space-lg); border-left: 4px solid #2196F3;">
          <strong>📋 Scheduling for:</strong><br>
          <span style="font-size: 1.1em; color: #1976D2;">
            <?= htmlspecialchars($prefilledApplicant['first_name'] . ' ' . $prefilledApplicant['last_name']) ?>
          </span><br>
          <small class="text-muted"><?= htmlspecialchars($prefilledApplicant['email']) ?></small>
        </div>
      <?php endif; ?>
      
      <div class="form-group">
        <label>Scholarship *</label>
        <select name="scholarship_id" class="form-input" required <?= $prefilledScholarshipId ? 'readonly style="background: #f5f5f5;"' : '' ?>>
          <option value="">Select scholarship</option>
          <?php foreach($scholarships as $sch): ?>
            <option value="<?= (int)$sch['id'] ?>" <?= ($prefilledScholarshipId && $prefilledScholarshipId == $sch['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($sch['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label>Interview Date *</label>
          <input type="date" name="interview_date" class="form-input" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Interview Time *</label>
          <input type="time" name="interview_time" class="form-input" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label>Duration (minutes)</label>
          <input type="number" name="duration_minutes" class="form-input" value="30" min="15" max="180">
        </div>
        <div class="form-group">
          <label>Max Applicants</label>
          <input type="number" name="max_applicants" class="form-input" value="1" min="1" max="10">
        </div>
      </div>
      
      <div class="form-group">
        <label>Interview Type *</label>
        <select name="interview_type" class="form-input" id="interviewType" onchange="toggleLocationFields()" required>
          <option value="online">Online</option>
          <option value="in-person">In-Person</option>
          <option value="phone">Phone</option>
        </select>
      </div>
      
      <div class="form-group" id="meetingLinkField">
        <label>Meeting Link (Zoom, Google Meet, etc.)</label>
        <input type="url" name="meeting_link" class="form-input" placeholder="https://zoom.us/j/...">
      </div>
      
      <div class="form-group" id="locationField" style="display: none;">
        <label>Location</label>
        <input type="text" name="location" class="form-input" placeholder="Room 101, Building A">
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Slot</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Slot Modal -->
<div id="editModal" class="modal">
  <div class="modal-content" style="max-width: 600px;">
    <div class="modal-header">
      <h2>✏️ Edit Interview Slot</h2>
      <span class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="update_slot">
      <input type="hidden" name="slot_id" id="edit_slot_id">
      
      <div class="form-group">
        <label>Scholarship *</label>
        <select name="scholarship_id" class="form-input" id="edit_scholarship_id" required>
          <option value="">Select scholarship</option>
          <?php foreach($scholarships as $sch): ?>
            <option value="<?= (int)$sch['id'] ?>"><?= htmlspecialchars($sch['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label>Interview Date *</label>
          <input type="date" name="interview_date" class="form-input" id="edit_interview_date" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>Interview Time *</label>
          <input type="time" name="interview_time" class="form-input" id="edit_interview_time" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
          <label>Duration (minutes)</label>
          <input type="number" name="duration_minutes" class="form-input" id="edit_duration_minutes" value="30" min="15" max="180">
        </div>
        <div class="form-group">
          <label>Max Applicants</label>
          <input type="number" name="max_applicants" class="form-input" id="edit_max_applicants" value="1" min="1" max="10">
        </div>
      </div>
      
      <div class="form-group">
        <label>Interview Type *</label>
        <select name="interview_type" class="form-input" id="edit_interview_type" onchange="toggleEditLocationFields()" required>
          <option value="online">Online</option>
          <option value="in-person">In-Person</option>
          <option value="phone">Phone</option>
        </select>
      </div>
      
      <div class="form-group" id="edit_meetingLinkField">
        <label>Meeting Link (Zoom, Google Meet, etc.)</label>
        <input type="url" name="meeting_link" class="form-input" id="edit_meeting_link" placeholder="https://zoom.us/j/...">
      </div>
      
      <div class="form-group" id="edit_locationField" style="display: none;">
        <label>Location</label>
        <input type="text" name="location" class="form-input" id="edit_location" placeholder="Room 101, Building A">
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Slot</button>
      </div>
    </form>
  </div>
</div>

<script>
// Store slots data for editing
const slotsData = <?= json_encode($slots) ?>;

function toggleLocationFields() {
  const type = document.getElementById('interviewType').value;
  const meetingLink = document.getElementById('meetingLinkField');
  const location = document.getElementById('locationField');
  
  if (type === 'online') {
    meetingLink.style.display = 'block';
    location.style.display = 'none';
  } else {
    meetingLink.style.display = 'none';
    location.style.display = 'block';
  }
}

function toggleEditLocationFields() {
  const type = document.getElementById('edit_interview_type').value;
  const meetingLink = document.getElementById('edit_meetingLinkField');
  const location = document.getElementById('edit_locationField');
  
  if (type === 'online') {
    meetingLink.style.display = 'block';
    location.style.display = 'none';
  } else {
    meetingLink.style.display = 'none';
    location.style.display = 'block';
  }
}

function openEditModal(slotId) {
  const slot = slotsData.find(s => s.id == slotId);
  if (!slot) return;
  
  // Populate form fields
  document.getElementById('edit_slot_id').value = slot.id;
  document.getElementById('edit_scholarship_id').value = slot.scholarship_id;
  document.getElementById('edit_interview_date').value = slot.interview_date;
  document.getElementById('edit_interview_time').value = slot.interview_time;
  document.getElementById('edit_duration_minutes').value = slot.duration_minutes;
  document.getElementById('edit_max_applicants').value = slot.max_applicants;
  document.getElementById('edit_interview_type').value = slot.interview_type;
  document.getElementById('edit_meeting_link').value = slot.meeting_link || '';
  document.getElementById('edit_location').value = slot.location || '';
  
  // Toggle fields based on type
  toggleEditLocationFields();
  
  // Show modal
  document.getElementById('editModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
