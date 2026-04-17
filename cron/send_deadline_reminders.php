<?php
/**
 * Cron script: send deadline reminders to students
 * Run daily via cron or Task Scheduler.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/NotificationHelper.php';

$pdo = getPDO();

// Reminder map: days before deadline => reminder_type
$map = [7 => '7_days', 1 => '1_day', 0 => 'deadline'];

foreach ($map as $days => $type) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE status = "open" AND DATE(deadline) = DATE_ADD(CURDATE(), INTERVAL :d DAY)');
        $stmt->execute([':d' => $days]);
        $schs = $stmt->fetchAll();
        foreach ($schs as $sch) {
            $users = $pdo->query("SELECT id, email, first_name FROM users WHERE role = 'student' AND active = 1")->fetchAll();
            foreach ($users as $u) {
                // ensure we don't send duplicate reminders
                try {
                    $exists = $pdo->prepare('SELECT id FROM deadline_reminders WHERE user_id = :uid AND scholarship_id = :sid AND reminder_type = :rt LIMIT 1');
                    $exists->execute([':uid' => $u['id'], ':sid' => $sch['id'], ':rt' => $type]);
                    if ($exists->fetch()) continue;
                    $pdo->prepare('INSERT INTO deadline_reminders (user_id, scholarship_id, reminder_type, sent, sent_at, created_at) VALUES (:uid, :sid, :rt, 1, NOW(), NOW())')
                        ->execute([':uid' => $u['id'], ':sid' => $sch['id'], ':rt' => $type]);
                } catch (Exception $e) { /* table may not exist */ }

                $msg = 'Reminder: "' . $sch['title'] . '" deadline is ' . ($days === 0 ? 'today' : 'in ' . $days . ' day(s)') . ' (' . $sch['deadline'] . ').';
                notifyStudent($pdo, (int)$u['id'], 'Scholarship Deadline Reminder', $msg, 'deadline', null, (int)$sch['id']);
            }
        }
    } catch (Exception $e) {
        error_log('[deadline_reminders] ' . $e->getMessage());
    }
}

echo "Deadline reminders processed.\n";
