<?php
/**
 * Debug Email Issue
 */

echo "<h2>🔍 Email Debug</h2>";

// Check if config/email.php can be loaded
echo "<h3>1. Loading email.php</h3>";
try {
    require_once __DIR__ . '/config/email.php';
    echo "<p>✅ email.php loaded successfully</p>";
} catch (Exception $e) {
    echo "<p>❌ Error loading email.php: " . $e->getMessage() . "</p>";
}

// Check if queueEmail function exists
echo "<h3>2. Function Check</h3>";
echo "<p>queueEmail exists: " . (function_exists('queueEmail') ? '✅ YES' : '❌ NO') . "</p>";
echo "<p>sendEmail exists: " . (function_exists('sendEmail') ? '✅ YES' : '❌ NO') . "</p>";
echo "<p>getPDO exists: " . (function_exists('getPDO') ? '✅ YES' : '❌ NO') . "</p>";

// Check database connection
echo "<h3>3. Database Connection</h3>";
try {
    require_once __DIR__ . '/config/db.php';
    $pdo = getPDO();
    echo "<p>✅ Database connected</p>";
    
    // Get application details
    $stmt = $pdo->prepare('SELECT a.*, u.email, u.first_name, s.title as scholarship_title 
                           FROM applications a 
                           LEFT JOIN users u ON a.user_id = u.id 
                           LEFT JOIN scholarships s ON a.scholarship_id = s.id 
                           WHERE a.id = 1');
    $stmt->execute();
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($app) {
        echo "<p>✅ Application found:</p>";
        echo "<ul>";
        echo "<li>ID: " . $app['id'] . "</li>";
        echo "<li>User Email: " . htmlspecialchars($app['email']) . "</li>";
        echo "<li>User Name: " . htmlspecialchars($app['first_name']) . "</li>";
        echo "<li>Scholarship: " . htmlspecialchars($app['scholarship_title']) . "</li>";
        echo "<li>Status: " . htmlspecialchars($app['status']) . "</li>";
        echo "</ul>";
        
        // Try to queue an email manually
        echo "<h3>4. Manual Email Queue Test</h3>";
        if (function_exists('queueEmail')) {
            $subject = 'TEST: Application Status Update';
            $body = '<p>This is a test email for application ID ' . $app['id'] . '</p>';
            
            echo "<p>Attempting to queue email to: " . htmlspecialchars($app['email']) . "</p>";
            
            try {
                $result = queueEmail($app['email'], $subject, $body, $app['user_id']);
                if ($result) {
                    echo "<p>✅ Email queued/sent successfully!</p>";
                    echo "<p>Check email_logs table or your inbox.</p>";
                } else {
                    echo "<p>❌ queueEmail returned false</p>";
                }
            } catch (Exception $e) {
                echo "<p>❌ Error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>❌ queueEmail function not found!</p>";
        }
    } else {
        echo "<p>❌ Application not found</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Check email logs
echo "<h3>5. Recent Email Logs</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 3");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($logs) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Subject</th><th>Status</th><th>Created</th></tr>";
        foreach ($logs as $log) {
            echo "<tr>";
            echo "<td>" . $log['id'] . "</td>";
            echo "<td>" . htmlspecialchars($log['email']) . "</td>";
            echo "<td>" . htmlspecialchars($log['subject']) . "</td>";
            echo "<td>" . $log['status'] . "</td>";
            echo "<td>" . $log['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>📝 Instructions:</h3>";
echo "<ol>";
echo "<li>If queueEmail exists and test succeeded, check your email inbox</li>";
echo "<li>If queueEmail doesn't exist, there's a problem with email.php</li>";
echo "<li>Go to <a href='staff/cron.php'>staff/cron.php</a> and run process_email_queue.php</li>";
echo "<li>Then check email again</li>";
echo "</ol>";
?>
