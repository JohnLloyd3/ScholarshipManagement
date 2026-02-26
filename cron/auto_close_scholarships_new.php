<?php
/**
 * Cron Job: Auto-close scholarships past deadline
 * Schedule this to run daily via cron: /usr/bin/php /path/to/cron/auto_close_scholarships.php
 * Or visit it via URL to test
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

try {
    $pdo = getPDO();
    
    // ==========================================
    // 1. AUTO-CLOSE SCHOLARSHIPS PAST DEADLINE
    // ==========================================
    
    $stmt = $pdo->query("
        SELECT id, title FROM scholarships
        WHERE status = 'open' AND deadline <= NOW()
    ");
    $to_close = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    foreach ($to_close as $scholarship) {
        // Mark as closed
        $pdo->prepare("UPDATE scholarships SET status = 'closed' WHERE id = :id")
            ->execute([':id' => $scholarship['id']]);
        
        // Notify remaining pending applicants
        $appStmt = $pdo->prepare("
            SELECT DISTINCT a.user_id, u.email, u.first_name
            FROM applications a
            JOIN users u ON a.user_id = u.id
            WHERE a.scholarship_id = :sch_id AND a.status = 'pending'
        ");
        $appStmt->execute([':sch_id' => $scholarship['id']]);
        $applicants = $appStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        foreach ($applicants as $applicant) {
            // Create notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_scholarship_id)
                VALUES (:user_id, 'Application Status', :message, 'warning', :sch_id)
            ");
            $notifStmt->execute([
                ':user_id' => $applicant['user_id'],
                ':message' => 'The scholarship "' . $scholarship['title'] . '" has closed. No further updates expected.',
                ':sch_id' => $scholarship['id']
            ]);
            
            // Send email
            sendEmail($applicant['email'], 'Scholarship Closed - ' . $scholarship['title'],
                "<h2>Scholarship Closed</h2><p>Dear " . htmlspecialchars($applicant['first_name']) . ",</p><p>The scholarship \"" . htmlspecialchars($scholarship['title']) . "\" has now closed. Thank you for your interest!</p>", true);
        }
        
        echo "✓ Closed scholarship: {$scholarship['title']}\n";
    }
    
    // ==========================================
    // 2. SEND 7-DAY DEADLINE REMINDERS
    // ==========================================
    
    $stmt = $pdo->query("
        SELECT s.id, s.title, s.deadline FROM scholarships s
        WHERE s.status = 'open'
        AND DATE(s.deadline) = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $reminder_7_days = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    foreach ($reminder_7_days as $scholarship) {
        // Get users who haven't applied yet
        $userStmt = $pdo->prepare("
            SELECT u.id, u.email, u.first_name FROM users u
            WHERE u.role = 'student'
            AND u.active = 1
            AND u.id NOT IN (
                SELECT user_id FROM applications
                WHERE scholarship_id = :sch_id
            )
        ");
        $userStmt->execute([':sch_id' => $scholarship['id']]);
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        foreach ($users as $user) {
            // Check if reminder already sent
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM deadline_reminders
                WHERE user_id = :user_id AND scholarship_id = :sch_id
                AND reminder_type = '7_days' AND sent = 1
            ");
            $checkStmt->execute([':user_id' => $user['id'], ':sch_id' => $scholarship['id']]);
            
            if ($checkStmt->fetchColumn() === 0) {
                // Create reminder
                $remStmt = $pdo->prepare("
                    INSERT INTO deadline_reminders (user_id, scholarship_id, reminder_type, sent, sent_at)
                    VALUES (:user_id, :sch_id, '7_days', 1, NOW())
                    ON DUPLICATE KEY UPDATE sent = 1, sent_at = NOW()
                ");
                $remStmt->execute([':user_id' => $user['id'], ':sch_id' => $scholarship['id']]);
                
                // Send email
                $emailBody = "
                    <h2>Scholarship Reminder: 7 Days Left!</h2>
                    <p>Dear " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>There are only <strong>7 days left</strong> to apply for the <strong>" . htmlspecialchars($scholarship['title']) . "</strong> scholarship.</p>
                    <p>Deadline: " . $scholarship['deadline'] . "</p>
                    <p><a href='" . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/member/apply_scholarship_new.php?id=' . $scholarship['id'] : '#') . "'>Apply Now</a></p>
                ";
                sendEmail($user['email'], 'Scholarship Deadline Reminder - ' . $scholarship['title'], $emailBody, true);
            }
        }
        
        echo "✓ Sent 7-day reminders for: {$scholarship['title']}\n";
    }
    
    // ==========================================
    // 3. SEND 1-DAY DEADLINE REMINDERS
    // ==========================================
    
    $stmt = $pdo->query("
        SELECT s.id, s.title, s.deadline FROM scholarships s
        WHERE s.status = 'open'
        AND DATE(s.deadline) = CURDATE() + INTERVAL 1 DAY
    ");
    $reminder_1_day = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    foreach ($reminder_1_day as $scholarship) {
        $userStmt = $pdo->prepare("
            SELECT u.id, u.email, u.first_name FROM users u
            WHERE u.role = 'student'
            AND u.active = 1
            AND u.id NOT IN (
                SELECT user_id FROM applications
                WHERE scholarship_id = :sch_id
            )
        ");
        $userStmt->execute([':sch_id' => $scholarship['id']]);
        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        foreach ($users as $user) {
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM deadline_reminders
                WHERE user_id = :user_id AND scholarship_id = :sch_id
                AND reminder_type = '1_day' AND sent = 1
            ");
            $checkStmt->execute([':user_id' => $user['id'], ':sch_id' => $scholarship['id']]);
            
            if ($checkStmt->fetchColumn() === 0) {
                $remStmt = $pdo->prepare("
                    INSERT INTO deadline_reminders (user_id, scholarship_id, reminder_type, sent, sent_at)
                    VALUES (:user_id, :sch_id, '1_day', 1, NOW())
                    ON DUPLICATE KEY UPDATE sent = 1, sent_at = NOW()
                ");
                $remStmt->execute([':user_id' => $user['id'], ':sch_id' => $scholarship['id']]);
                
                $emailBody = "
                    <h2>URGENT: Scholarship Deadline - 1 Day Left!</h2>
                    <p>Dear " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>This is your FINAL REMINDER! Only <strong>1 day left</strong> to apply for the <strong>" . htmlspecialchars($scholarship['title']) . "</strong> scholarship.</p>
                    <p>Deadline: " . $scholarship['deadline'] . "</p>
                    <p><a href='" . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/member/apply_scholarship_new.php?id=' . $scholarship['id'] : '#') . "'>Apply Immediately</a></p>
                ";
                sendEmail($user['email'], 'URGENT: Last Chance - ' . $scholarship['title'], $emailBody, true);
            }
        }
        
        echo "✓ Sent 1-day reminders for: {$scholarship['title']}\n";
    }
    
    echo "\n✓ Cron job completed successfully!\n";
    
} catch (Exception $e) {
    error_log('[Cron Job Error] ' . $e->getMessage());
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
