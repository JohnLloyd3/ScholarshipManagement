<?php
/**
 * Cron script: send deadline reminders to students
 * Run daily via cron or Task Scheduler.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';

$pdo = getPDO();

// Reminder map: days before deadline => reminder_type
$map = [7 => '7_days', 1 => '1_day', 0 => 'deadline'];

foreach ($map as $days => $type) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE status = "open" AND DATE(deadline) = DATE_ADD(CURDATE(), INTERVAL :d DAY)');
        $stmt->execute([':d' => $days]);
        $schs = $stmt->fetchAll();
        foreach ($schs as $sch) {
            // notify all active students
            $users = $pdo->query("SELECT id, email, first_name FROM users WHERE role = 'student' AND active = 1")->fetchAll();
            foreach ($users as $u) {
                // ensure we don't send duplicate reminders
                $exists = $pdo->prepare('SELECT id FROM deadline_reminders WHERE user_id = :uid AND scholarship_id = :sid AND reminder_type = :rt LIMIT 1');
                $exists->execute([':uid' => $u['id'], ':sid' => $sch['id'], ':rt' => $type]);
                if ($exists->fetch()) continue;

                // record reminder
                $ins = $pdo->prepare('INSERT INTO deadline_reminders (user_id, scholarship_id, reminder_type, sent, sent_at, created_at) VALUES (:uid, :sid, :rt, 1, NOW(), NOW())');
                $ins->execute([':uid' => $u['id'], ':sid' => $sch['id'], ':rt' => $type]);

                // create in-app notification
                $title = 'Scholarship Deadline Reminder';
                $msg = 'Reminder: "' . $sch['title'] . '" deadline is in ' . ($days === 0 ? 'today' : $days . ' day(s)') . '.';
                $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_scholarship_id, created_at) VALUES (:uid, :title, :msg, :type, :sid, NOW())')
                    ->execute([':uid' => $u['id'], ':title' => $title, ':msg' => $msg, ':type' => 'deadline', ':sid' => $sch['id']]);

                // queue email
                $body = '<p>Dear ' . htmlspecialchars($u['first_name'] ?? '') . ',</p><p>This is a reminder that the scholarship <strong>' . htmlspecialchars($sch['title']) . '</strong> has a deadline on ' . htmlspecialchars($sch['deadline']) . '.</p>';
                queueEmail($u['email'], $title, $body, $u['id']);
            }
        }
    } catch (Exception $e) {
        error_log('[deadline_reminders] ' . $e->getMessage());
    }
}

echo "Deadline reminders processed.\n";
