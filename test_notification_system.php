<?php
/**
 * Test Notification System
 * Verifies that notifications and emails are working
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/email.php';

echo "<h2>🧪 Notification System Test</h2>";

$pdo = getPDO();

// Check if tables exist
echo "<h3>1. Database Tables Check</h3>";
$tables = ['notifications', 'email_logs', 'applications', 'users'];
foreach ($tables as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "<p>✅ <strong>$table</strong>: $count rows</p>";
    } catch (Exception $e) {
        echo "<p>❌ <strong>$table</strong>: NOT FOUND</p>";
    }
}

// Check recent notifications
echo "<hr><h3>2. Recent Notifications</h3>";
try {
    $stmt = $pdo->query("SELECT n.*, u.email FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 5");
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($notifs) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>User Email</th><th>Title</th><th>Message</th><th>Created</th></tr>";
        foreach ($notifs as $n) {
            echo "<tr>";
            echo "<td>" . $n['id'] . "</td>";
            echo "<td>" . htmlspecialchars($n['email'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($n['title']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($n['message'], 0, 50)) . "...</td>";
            echo "<td>" . htmlspecialchars($n['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No notifications found.</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

// Check email queue
echo "<hr><h3>3. Email Queue Status</h3>";
try {
    $queued = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'queued'")->fetchColumn();
    $sent = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'")->fetchColumn();
    $failed = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'")->fetchColumn();
    
    echo "<p>📧 <strong>Queued:</strong> $queued</p>";
    echo "<p>✅ <strong>Sent:</strong> $sent</p>";
    echo "<p>❌ <strong>Failed:</strong> $failed</p>";
    
    // Show recent emails
    $stmt = $pdo->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 5");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($emails) {
        echo "<h4>Recent Emails:</h4>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>To</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Created</th></tr>";
        foreach ($emails as $e) {
            $statusColor = $e['status'] === 'sent' ? 'green' : ($e['status'] === 'failed' ? 'red' : 'orange');
            echo "<tr>";
            echo "<td>" . $e['id'] . "</td>";
            echo "<td>" . htmlspecialchars($e['email']) . "</td>";
            echo "<td>" . htmlspecialchars($e['subject']) . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . strtoupper($e['status']) . "</td>";
            echo "<td>" . $e['attempts'] . "</td>";
            echo "<td>" . htmlspecialchars($e['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

// Test email sending
echo "<hr><h3>4. Email Configuration Test</h3>";
echo "<p><strong>SMTP Enabled:</strong> " . (SMTP_ENABLED ? 'Yes' : 'No') . "</p>";
echo "<p><strong>SMTP Host:</strong> " . SMTP_HOST . "</p>";
echo "<p><strong>SMTP Port:</strong> " . SMTP_PORT . "</p>";
echo "<p><strong>SMTP User:</strong> " . SMTP_USER . "</p>";
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? '✅ Enabled' : '❌ Disabled') . "</p>";

echo "<hr><h3>5. Quick Actions</h3>";
echo "<p><a href='test_email_now.php' style='padding: 10px 20px; background: #c41e3a; color: white; text-decoration: none; border-radius: 5px;'>📧 Send Test Email</a></p>";
echo "<p><a href='staff/cron.php' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>⚙️ Process Email Queue</a></p>";
echo "<p><a href='admin/email_queue.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>📬 View Email Queue</a></p>";

echo "<hr>";
echo "<h3>✅ Next Steps:</h3>";
echo "<ol>";
echo "<li>Update an application status in <a href='staff/applications.php'>staff/applications.php</a></li>";
echo "<li>Check if notification appears in database (refresh this page)</li>";
echo "<li>Check if email is queued (refresh this page)</li>";
echo "<li>Go to <a href='staff/cron.php'>staff/cron.php</a> and run process_email_queue.php</li>";
echo "<li>Check your email inbox!</li>";
echo "</ol>";
?>
