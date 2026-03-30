<?php
/**
 * Feedback Helper — quick star-rating feedback per application
 */

function submitFeedback(PDO $pdo, int $userId, int $applicationId, int $rating, ?string $comment): int {
    // Get scholarship_id from application
    $stmt = $pdo->prepare('SELECT scholarship_id FROM applications WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $applicationId, ':uid' => $userId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) throw new RuntimeException('Application not found.');

    $ins = $pdo->prepare('INSERT INTO feedback (application_id, user_id, scholarship_id, rating, comment) VALUES (:app, :uid, :sch, :rating, :comment)');
    $ins->execute([':app' => $applicationId, ':uid' => $userId, ':sch' => $app['scholarship_id'], ':rating' => $rating, ':comment' => $comment]);
    return (int)$pdo->lastInsertId();
}

function feedbackExists(PDO $pdo, int $applicationId): bool {
    $stmt = $pdo->prepare('SELECT id FROM feedback WHERE application_id = :id LIMIT 1');
    $stmt->execute([':id' => $applicationId]);
    return (bool)$stmt->fetch();
}

function getAllFeedback(PDO $pdo, ?int $scholarshipId = null): array {
    try {
        $where = $scholarshipId ? 'WHERE f.scholarship_id = :sid' : '';
        $params = $scholarshipId ? [':sid' => $scholarshipId] : [];
        $stmt = $pdo->prepare("
            SELECT f.*, u.first_name, u.last_name, s.title AS scholarship_title
            FROM feedback f
            JOIN users u ON f.user_id = u.id
            JOIN scholarships s ON f.scholarship_id = s.id
            $where
            ORDER BY f.submitted_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getStudentFeedback(PDO $pdo, int $userId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT f.*, s.title AS scholarship_title
            FROM feedback f
            JOIN scholarships s ON f.scholarship_id = s.id
            WHERE f.user_id = :uid
            ORDER BY f.submitted_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getFeedbackAnalytics(PDO $pdo, ?int $scholarshipId = null): array {
    try {
        $where = $scholarshipId ? 'WHERE scholarship_id = :sid' : '';
        $params = $scholarshipId ? [':sid' => $scholarshipId] : [];

        $stmt = $pdo->prepare("SELECT rating, COUNT(*) as cnt FROM feedback $where GROUP BY rating");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    $counts = array_fill(1, 5, 0);
    $total  = 0;
    $sum    = 0;
    foreach ($rows as $r) {
        $counts[(int)$r['rating']] = (int)$r['cnt'];
        $total += (int)$r['cnt'];
        $sum   += (int)$r['rating'] * (int)$r['cnt'];
    }

    return [
        'total'   => $total,
        'average' => $total > 0 ? round($sum / $total, 1) : 0,
        'counts'  => $counts,
    ];
}

/**
 * Get eligible applications for feedback (approved/completed, no feedback yet)
 */
function getEligibleFeedbackApplications(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, s.title AS scholarship_title
        FROM applications a
        JOIN scholarships s ON a.scholarship_id = s.id
        LEFT JOIN feedback f ON f.application_id = a.id
        WHERE a.user_id = :uid
          AND a.status IN ('approved', 'completed')
          AND f.id IS NULL
        ORDER BY a.updated_at DESC
    ");
    $stmt->execute([':uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
