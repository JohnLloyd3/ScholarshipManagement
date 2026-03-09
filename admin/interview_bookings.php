<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

requireLogin();
requireAnyRole(['admin', 'staff'], 'Admin or Staff access required');

$pdo = getPDO();
$slotId = isset($_GET['slot_id']) ? (int)$_GET['slot_id'] : null;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_booking_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        
        if ($bookingId && in_array($status, ['confirmed', 'completed', 'cancelled', 'no-show'])) {
            $stmt = $pdo->prepare('UPDATE interview_bookings SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $status, ':id' => $bookingId]);
            
            if ($status === 'confirmed') {
                $confirmStmt = $pdo->prepare('UPDATE interview_bookings SET confirmed_at = NOW() WHERE id = :id');
                $confirmStmt->execute([':id' => $bookingId]);
            }
            
            $_SESSION['success'] = 'Booking status updated.';
        }
    }
    
    header('Location: interview_bookings.php' . ($slotId ? '?slot_id=' . $slotId : ''));
    exit;
}

// Get bookings
if ($slotId) {
    // Get specific slot bookings
    $stmt = $pdo->prepare('
        SELECT 
            b.*,
            s.interview_date,
            s.interview_time,
            s.duration_minutes,
            s.interview_type,
            s.location,
            s.meeting_link,
            u.first_name, u.last_name, u.email,
            sch.title as scholarship_title,
            a.status as application_status
        FROM interview_bookings b
        JOIN interview_slots s ON b.slot_id = s.id
        JOIN users u ON b.user_id = u.id
        JOIN applications a ON b.application_id = a.id
        JOIN scholarships sch ON a.scholarship_id = sch.id
        WHERE b.slot_id = :slot_id
        ORDER BY b.booked_at DESC
    ');
    $stmt->execute([':slot_id' => $slotId]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get slot details
    $slotStmt = $pdo->prepare('
        SELECT s.*, sch.title as scholarship_title
        FROM interview_slots s
        JOIN scholarships sch ON s.scholarship_id = sch.id
        WHERE s.id = :id
    ');
    $slotStmt->execute([':id' => $slotId]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Get all bookings
    $stmt = $pdo->query('
        SELECT 
            b.*,
            s.interview_date,
            s.interview_time,
            s.duration_minutes,
            s.interview_type,
            u.first_name, u.last_name, u.email,
            sch.title as scholarship_title,
            a.status as application_status
        FROM interview_bookings b
        JOIN interview_slots s ON b.slot_id = s.id
        JOIN users u ON b.user_id = u.id
        JOIN applications a ON b.application_id = a.id
        JOIN scholarships sch ON a.scholarship_id = sch.id
        ORDER BY s.interview_date DESC, s.interview_time DESC
        LIMIT 100
    ');
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $slot = null;
}

$page_title = 'Interview Bookings - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📅 Interview Bookings</h1>
  <?php if ($slot): ?>
    <p class="text-muted">
      <?= htmlspecialchars($slot['scholarship_title']) ?> - 
      <?= date('M d, Y g:i A', strtotime($slot['interview_date'] . ' ' . $slot['interview_time'])) ?>
    </p>
  <?php else: ?>
    <p class="text-muted">View and manage all interview bookings</p>
  <?php endif; ?>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<div class="content-card">
  <?php if ($slot): ?>
    <div style="margin-bottom: var(--space-lg); padding: var(--space-lg); background: var(--gray-50); border-radius: var(--radius-lg);">
      <h3>Slot Details</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-md); margin-top: var(--space-md);">
        <div>
          <strong>Type:</strong> <?= ucfirst($slot['interview_type']) ?>
        </div>
        <div>
          <strong>Duration:</strong> <?= (int)$slot['duration_minutes'] ?> minutes
        </div>
        <div>
          <strong>Max Applicants:</strong> <?= (int)$slot['max_applicants'] ?>
        </div>
        <div>
          <strong>Bookings:</strong> <?= count($bookings) ?> / <?= (int)$slot['max_applicants'] ?>
        </div>
      </div>
      <?php if ($slot['interview_type'] === 'online' && $slot['meeting_link']): ?>
        <div style="margin-top: var(--space-md);">
          <strong>Meeting Link:</strong> <a href="<?= htmlspecialchars($slot['meeting_link']) ?>" target="_blank"><?= htmlspecialchars($slot['meeting_link']) ?></a>
        </div>
      <?php elseif ($slot['location']): ?>
        <div style="margin-top: var(--space-md);">
          <strong>Location:</strong> <?= htmlspecialchars($slot['location']) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($bookings)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Applicant</th>
          <?php if (!$slotId): ?>
            <th>Scholarship</th>
            <th>Interview Date</th>
          <?php endif; ?>
          <th>Application Status</th>
          <th>Booking Status</th>
          <th>Booked At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($bookings as $booking): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? '')) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($booking['email'] ?? '') ?></small>
            </td>
            <?php if (!$slotId): ?>
              <td><?= htmlspecialchars($booking['scholarship_title'] ?? 'N/A') ?></td>
              <td>
                <?= date('M d, Y', strtotime($booking['interview_date'])) ?><br>
                <small class="text-muted"><?= date('g:i A', strtotime($booking['interview_time'])) ?></small>
              </td>
            <?php endif; ?>
            <td>
              <span class="status-badge status-<?= strtolower($booking['application_status']) ?>">
                <?= ucfirst(str_replace('_', ' ', $booking['application_status'])) ?>
              </span>
            </td>
            <td>
              <span class="status-badge status-<?= $booking['status'] ?>">
                <?= ucfirst($booking['status']) ?>
              </span>
            </td>
            <td><small><?= date('M d, Y g:i A', strtotime($booking['booked_at'])) ?></small></td>
            <td>
              <form method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="update_booking_status">
                <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                <select name="status" class="form-input" style="display: inline-block; width: auto; padding: 4px 8px; font-size: 0.875rem;">
                  <option value="scheduled" <?= $booking['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                  <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                  <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                  <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                  <option value="no-show" <?= $booking['status'] === 'no-show' ? 'selected' : '' ?>>No-Show</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Update</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">📅</div>
      <h3 class="empty-state-title">No Bookings</h3>
      <p class="empty-state-description">No interview bookings found for this slot.</p>
    </div>
  <?php endif; ?>
  
  <?php if ($slotId): ?>
    <div style="margin-top: var(--space-lg);">
      <a href="interview_slots.php" class="btn btn-ghost">← Back to Slots</a>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
