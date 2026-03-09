<?php
/**
 * Debug Interview Scheduling Email
 * Check if the email code is being reached
 */

require_once __DIR__ . '/config/db.php';

echo "<h2>Debug Interview Scheduling</h2>";

$pdo = getPDO();

// Get the most recent interview booking
$stmt = $pdo->query('
    SELECT ib.*, a.user_id, a.scholarship_id, s.interview_date, s.interview_time, s.duration_minutes, s.interview_type, s.location, s.meeting_link
    FROM interview_bookings ib
    JOIN applications a ON ib.application_id = a.id
    JOIN interview_slots s ON ib.slot_id = s.id
    ORDER BY ib.booked_at DESC
    LIMIT 1
');

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo "<p style='color: red;'>No interview bookings found in database.</p>";
    exit;
}

echo "<h3>Most Recent Interview Booking:</h3>";
echo "<pre>";
print_r($booking);
echo "</pre>";

// Get user info
$userStmt = $pdo->prepare('
    SELECT u.*, s.title as scholarship_title 
    FROM users u, applications a, scholarships s 
    WHERE u.id = :uid AND a.id = :aid AND s.id = a.scholarship_id
');
$userStmt->execute([':uid' => $booking['user_id'], ':aid' => $booking['application_id']]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>User Info:</h3>";
if ($userInfo) {
    echo "<pre>";
    print_r($userInfo);
    echo "</pre>";
    
    echo "<p><strong>Email will be sent to:</strong> " . htmlspecialchars($userInfo['email']) . "</p>";
    
    if (empty($userInfo['email'])) {
        echo "<p style='color: red;'>❌ ERROR: User email is empty!</p>";
    } else {
        echo "<p style='color: green;'>✅ User email is valid</p>";
    }
} else {
    echo "<p style='color: red;'>❌ ERROR: Could not fetch user info!</p>";
    echo "<p>User ID: " . $booking['user_id'] . "</p>";
    echo "<p>Application ID: " . $booking['application_id'] . "</p>";
}

// Check notifications
$notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 3');
$notifStmt->execute([':uid' => $booking['user_id']]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Recent Notifications for this user:</h3>";
if ($notifications) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Title</th><th>Message</th><th>Created At</th></tr>";
    foreach ($notifications as $notif) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($notif['title']) . "</td>";
        echo "<td>" . htmlspecialchars($notif['message']) . "</td>";
        echo "<td>" . htmlspecialchars($notif['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No notifications found</p>";
}

// Check email logs
$emailStmt = $pdo->prepare('SELECT * FROM email_logs WHERE recipient_email = :email ORDER BY created_at DESC LIMIT 5');
$emailStmt->execute([':email' => $userInfo['email'] ?? '']);
$emails = $emailStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Recent Email Logs for this user:</h3>";
if ($emails) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Subject</th><th>Status</th><th>Attempts</th><th>Created At</th><th>Sent At</th></tr>";
    foreach ($emails as $email) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($email['subject']) . "</td>";
        echo "<td>" . htmlspecialchars($email['status']) . "</td>";
        echo "<td>" . htmlspecialchars($email['attempts']) . "</td>";
        echo "<td>" . htmlspecialchars($email['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($email['sent_at'] ?? 'Not sent') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No email logs found for this user!</p>";
    echo "<p>This means the email sending code was never executed.</p>";
}

echo "<hr>";
echo "<p><a href='admin/interview_slots.php'>← Back to Interview Slots</a></p>";
?>
