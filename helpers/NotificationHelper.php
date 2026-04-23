<?php
/**
 * Notification Helper
 * Creates in-app notification AND sends email
 */

function notifyStudent(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'info',
    ?int $applicationId = null,
    ?int $scholarshipId = null
): void {
    try {
        $pdo->prepare("
            INSERT INTO notifications
                (user_id, title, message, type, related_application_id, related_scholarship_id, created_at)
            VALUES
                (:uid, :title, :msg, :type, :aid, :sid, NOW())
        ")->execute([
            ':uid' => $userId,
            ':title' => $title,
            ':msg' => $message,
            ':type' => $type,
            ':aid' => $applicationId,
            ':sid' => $scholarshipId,
        ]);
    } catch (Exception $e) {
        error_log('[notifyStudent] notification insert: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            if (!function_exists('queueEmail')) {
                require_once __DIR__ . '/../config/email.php';
            }
            $firstName = htmlspecialchars($user['first_name'] ?? 'Student');
            $emailBody = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;"><div style="background:#2563eb;color:white;padding:20px;text-align:center;"><h2>ScholarHub</h2></div><div style="padding:24px;background:#f9f9f9;"><p>Dear ' . $firstName . ',</p><p>' . htmlspecialchars($message) . '</p></div></div>';
            queueEmail($user['email'], $title . ' - ScholarHub', $emailBody, $userId);
        }
    } catch (Exception $e) {
        error_log('[notifyStudent] email send: ' . $e->getMessage());
    }
}
?>
