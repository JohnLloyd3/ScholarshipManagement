<?php
/**
 * Notification Helper
 * Creates an in-app notification AND sends a real email to the student.
 * Use this everywhere instead of bare INSERT INTO notifications.
 */

/**
 * Notify a student: inserts in-app notification + queues email.
 *
 * @param PDO    $pdo
 * @param int    $userId          Recipient user ID
 * @param string $title           Short notification title
 * @param string $message         Full message body
 * @param string $type            success | error | warning | info | application | deadline
 * @param int|null $applicationId Related application ID (optional)
 * @param int|null $scholarshipId Related scholarship ID (optional)
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
    // 1. In-app notification
    try {
        $pdo->prepare("
            INSERT INTO notifications
                (user_id, title, message, type, related_application_id, related_scholarship_id, created_at)
            VALUES
                (:uid, :title, :msg, :type, :aid, :sid, NOW())
        ")->execute([
            ':uid'   => $userId,
            ':title' => $title,
            ':msg'   => $message,
            ':type'  => $type,
            ':aid'   => $applicationId,
            ':sid'   => $scholarshipId,
        ]);
    } catch (Exception $e) {
        error_log('[notifyStudent] notification insert: ' . $e->getMessage());
    }

    // 2. Email to student's registered address
    try {
        $stmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && filter_var($user['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            if (!function_exists('queueEmail')) {
                require_once __DIR__ . '/../config/email.php';
            }

            $firstName = htmlspecialchars($user['first_name'] ?? 'Student');
            $emailBody = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
              <div style="background:#c41e3a;color:white;padding:20px;text-align:center;border-radius:8px 8px 0 0;">
                <h2 style="margin:0;">🎓 ScholarHub</h2>
              </div>
              <div style="padding:24px;background:#f9f9f9;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;">
                <p style="font-size:16px;">Dear ' . $firstName . ',</p>
                <div style="background:white;border-left:4px solid #c41e3a;padding:16px;border-radius:4px;margin:16px 0;">
                  <strong style="font-size:16px;">' . htmlspecialchars($title) . '</strong>
                  <p style="margin:8px 0 0;color:#374151;">' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                <p style="color:#6b7280;font-size:14px;">Log in to your ScholarHub account to view more details.</p>
                <p style="color:#9ca3af;font-size:12px;margin-top:24px;">© ' . date('Y') . ' ScholarHub. This is an automated notification.</p>
              </div>
            </div>';

            queueEmail($user['email'], $title . ' — ScholarHub', $emailBody, $userId);
        }
    } catch (Exception $e) {
        error_log('[notifyStudent] email send: ' . $e->getMessage());
    }
}
