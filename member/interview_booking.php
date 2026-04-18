<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../config/email.php';

requireLogin();
requireRole('student', 'Student access required');

$pdo = getPDO();
$user = $_SESSION['user'] ?? [];
$userId = $_SESSION['user_id'];

// Handle booking action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: interview_booking.php');
        exit;
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'book_slot') {
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $appId = (int)($_POST['application_id'] ?? 0);
        
        if ($slotId && $appId) {
            // Check if slot is available
            $stmt = $pdo->prepare('
                SELECT s.*, 
                       COUNT(b.id) as bookings_count,
                       sch.title as scholarship_title
                FROM interview_slots s
                LEFT JOIN interview_bookings b ON s.id = b.slot_id AND b.status != "cancelled"
                LEFT JOIN scholarships sch ON s.scholarship_id = sch.id
                WHERE s.id = :id
                GROUP BY s.id
            ');
            $stmt->execute([':id' => $slotId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($slot && $slot['bookings_count'] < $slot['max_applicants']) {
                // Check if already booked
                $checkStmt = $pdo->prepare('
                    SELECT id FROM interview_bookings 
                    WHERE slot_id = :slot_id AND application_id = :app_id
                ');
                $checkStmt->execute([':slot_id' => $slotId, ':app_id' => $appId]);
                
                if (!$checkStmt->fetch()) {
                    // Create booking
                    $bookStmt = $pdo->prepare('
                        INSERT INTO interview_bookings 
                        (slot_id, application_id, user_id, status, booked_at)
                        VALUES (:slot_id, :app_id, :user_id, "scheduled", NOW())
                    ');
                    $bookStmt->execute([
                        ':slot_id' => $slotId,
                        ':app_id' => $appId,
                        ':user_id' => $userId
                    ]);
                    $bookingId = $pdo->lastInsertId();
                    
                    // Send notification
                    $notifStmt = $pdo->prepare('
                        INSERT INTO notifications 
                        (user_id, title, message, type, related_application_id, created_at)
                        VALUES (:uid, :title, :msg, "application", :app_id, NOW())
                    ');
                    $notifStmt->execute([
                        ':uid' => $userId,
                        ':title' => 'Interview Scheduled',
                        ':msg' => 'Your interview for "' . $slot['scholarship_title'] . '" has been scheduled for ' . date('M d, Y g:i A', strtotime($slot['interview_date'] . ' ' . $slot['interview_time'])),
                        ':app_id' => $appId
                    ]);
                    
                    // Send email
                    $emailSubject = 'Interview Scheduled - ' . $slot['scholarship_title'];
                    $emailBody = '<h2>Interview Scheduled</h2>';
                    $emailBody .= '<p>Dear ' . htmlspecialchars($user['first_name'] ?? 'Student') . ',</p>';
                    $emailBody .= '<p>Your interview has been scheduled:</p>';
                    $emailBody .= '<p><strong>Scholarship:</strong> ' . htmlspecialchars($slot['scholarship_title']) . '</p>';
                    $emailBody .= '<p><strong>Date:</strong> ' . date('F d, Y', strtotime($slot['interview_date'])) . '</p>';
                    $emailBody .= '<p><strong>Time:</strong> ' . date('g:i A', strtotime($slot['interview_time'])) . '</p>';
                    $emailBody .= '<p><strong>Duration:</strong> ' . $slot['duration_minutes'] . ' minutes</p>';
                    
                    if ($slot['interview_type'] === 'online' && $slot['meeting_link']) {
                        $emailBody .= '<p><strong>Meeting Link:</strong> <a href="' . htmlspecialchars($slot['meeting_link']) . '">' . htmlspecialchars($slot['meeting_link']) . '</a></p>';
                    } else {
                        $emailBody .= '<p><strong>Location:</strong> ' . htmlspecialchars($slot['location']) . '</p>';
                    }
                    
                    $emailBody .= '<p>Please be on time. Good luck!</p>';
                    $emailBody .= '<p>Best regards,<br>ScholarHub Team</p>';
                    
                    queueEmail($user['email'], $emailSubject, $emailBody, $userId);
                    
                    $_SESSION['success'] = 'Interview slot booked successfully! Check your email for details.';
                } else {
                    $_SESSION['flash'] = 'You have already booked this slot.';
                }
            } else {
                $_SESSION['flash'] = 'This slot is no longer available.';
            }
        }
    }
    
    if ($action === 'cancel_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        if ($bookingId) {
            $stmt = $pdo->prepare('
                UPDATE interview_bookings 
                SET status = "cancelled", cancelled_at = NOW()
                WHERE id = :id AND user_id = :user_id
            ');
            $stmt->execute([':id' => $bookingId, ':user_id' => $userId]);
            $_SESSION['success'] = 'Interview booking cancelled.';
        }
    }
    
    header('Location: interview_booking.php');
    exit;
}

// Get user's applications that are shortlisted/approved
$appsStmt = $pdo->prepare('
    SELECT a.*, s.title as scholarship_title
    FROM applications a
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.user_id = :user_id 
    AND a.status IN ("approved", "under_review")
    ORDER BY a.created_at DESC
');
$appsStmt->execute([':user_id' => $userId]);
$applications = $appsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's bookings
$bookingsStmt = $pdo->prepare('
    SELECT 
        b.*,
        s.interview_date,
        s.interview_time,
        s.duration_minutes,
        s.interview_type,
        s.location,
        s.meeting_link,
        sch.title as scholarship_title
    FROM interview_bookings b
    JOIN interview_slots s ON b.slot_id = s.id
    JOIN applications a ON b.application_id = a.id
    JOIN scholarships sch ON a.scholarship_id = sch.id
    WHERE b.user_id = :user_id
    ORDER BY s.interview_date DESC, s.interview_time DESC
');
$bookingsStmt->execute([':user_id' => $userId]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get available slots for user's applications
$availableSlots = [];
foreach ($applications as $app) {
    $slotsStmt = $pdo->prepare('
        SELECT 
            s.*,
            COUNT(b.id) as bookings_count,
            sch.title as scholarship_title
        FROM interview_slots s
        LEFT JOIN interview_bookings b ON s.id = b.slot_id AND b.status != "cancelled"
        LEFT JOIN scholarships sch ON s.scholarship_id = sch.id
        WHERE s.scholarship_id = :sch_id
        AND s.interview_date >= CURDATE()
        GROUP BY s.id
        HAVING bookings_count < s.max_applicants
        ORDER BY s.interview_date ASC, s.interview_time ASC
    ');
    $slotsStmt->execute([':sch_id' => $app['scholarship_id']]);
    $slots = $slotsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($slots)) {
        $availableSlots[$app['id']] = [
            'application' => $app,
            'slots' => $slots
        ];
    }
}

$page_title = 'Interview Booking - ScholarHub';
$csrf_token = generateCSRFToken();
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📅 Interview Scheduling</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- My Bookings -->
<div class="content-card">
  <h2>📋 My Interview Bookings</h2>
  
  <?php if (!empty($bookings)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Scholarship</th>
          <th>Date & Time</th>
          <th>Type</th>
          <th>Details</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($bookings as $booking): ?>
          <tr>
            <td><strong><?= htmlspecialchars($booking['scholarship_title']) ?></strong></td>
            <td>
              <?= date('M d, Y', strtotime($booking['interview_date'])) ?><br>
              <small class="text-muted"><?= date('g:i A', strtotime($booking['interview_time'])) ?></small>
            </td>
            <td>
              <span class="status-badge status-<?= $booking['interview_type'] ?>">
                <?= ucfirst($booking['interview_type']) ?>
              </span>
            </td>
            <td>
              <?php if ($booking['interview_type'] === 'online' && $booking['meeting_link']): ?>
                <a href="<?= htmlspecialchars($booking['meeting_link']) ?>" target="_blank" class="btn btn-primary btn-sm">🔗 Join Meeting</a>
              <?php else: ?>
                <?= htmlspecialchars($booking['location'] ?: 'N/A') ?>
              <?php endif; ?>
            </td>
            <td>
              <span class="status-badge status-<?= $booking['status'] ?>">
                <?= ucfirst($booking['status']) ?>
              </span>
            </td>
            <td>
              <?php if ($booking['status'] === 'scheduled'): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel this booking?')">
                  <input type="hidden" name="action" value="cancel_booking">
                  <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">❌ Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📅</div>
      <h3 class="empty-state-title">No Bookings Yet</h3>
      <p class="empty-state-description">Book an interview slot below to get started.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Available Slots -->
<div class="content-card" style="margin-top: var(--space-xl);">
  <h2>🎯 Available Interview Slots</h2>
  
  <?php if (!empty($availableSlots)): ?>
    <?php foreach($availableSlots as $appId => $data): ?>
      <div style="margin-bottom: var(--space-xl);">
        <h3 style="color: var(--primary-color);">
          <?= htmlspecialchars($data['application']['scholarship_title']) ?>
        </h3>
        
        <table class="modern-table">
          <thead>
            <tr>
              <th>Date & Time</th>
              <th>Type</th>
              <th>Duration</th>
              <th>Available Spots</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($data['slots'] as $slot): ?>
              <tr>
                <td>
                  <?= date('M d, Y', strtotime($slot['interview_date'])) ?><br>
                  <small class="text-muted"><?= date('g:i A', strtotime($slot['interview_time'])) ?></small>
                </td>
                <td>
                  <span class="status-badge status-<?= $slot['interview_type'] ?>">
                    <?= ucfirst($slot['interview_type']) ?>
                  </span>
                </td>
                <td><?= (int)$slot['duration_minutes'] ?> min</td>
                <td>
                  <?= ((int)$slot['max_applicants'] - (int)$slot['bookings_count']) ?> / <?= (int)$slot['max_applicants'] ?>
                </td>
                <td>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="book_slot">
                    <input type="hidden" name="slot_id" value="<?= (int)$slot['id'] ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$appId ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">📅 Book Slot</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">🎯</div>
      <h3 class="empty-state-title">No Available Slots</h3>
      <p class="empty-state-description">
        <?php if (empty($applications)): ?>
          You don't have any shortlisted applications yet.
        <?php else: ?>
          No interview slots are currently available for your applications.
        <?php endif; ?>
      </p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
