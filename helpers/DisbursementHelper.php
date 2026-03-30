<?php
/**
 * Disbursement Helper
 * Query functions and notification helpers for financial tracking.
 * Uses disbursements -> applications -> scholarships (no awards table).
 */

/**
 * Fetch disbursements with optional filters.
 * Role-aware: students only see their own records.
 */
function getDisbursements(PDO $pdo, array $filters = [], string $role = 'admin', int $userId = 0): array {
    // Check if deleted_at column exists
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'deleted_at'")->fetch();
        $where = $colCheck ? ['d.deleted_at IS NULL'] : ['1=1'];
    } catch (Exception $e) {
        $where = ['1=1'];
    }
    $params = [];

    if ($role === 'student') {
        $where[] = 'd.user_id = :uid';
        $params[':uid'] = $userId;
    }
    if (!empty($filters['status'])) {
        $where[] = 'd.status = :status';
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'd.disbursement_date >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'd.disbursement_date <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }
    if (!empty($filters['student'])) {
        $where[] = "(u.first_name LIKE :student OR u.last_name LIKE :student OR CONCAT(u.first_name,' ',u.last_name) LIKE :student)";
        $params[':student'] = '%' . $filters['student'] . '%';
    }

    // Check if disbursement_date column exists (may be created_at only)
    try {
        $hasDate = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'disbursement_date'")->fetch();
    } catch (Exception $e) {
        $hasDate = false;
    }
    $dateCol = $hasDate ? 'd.disbursement_date' : 'd.created_at';

    $sql = "SELECT d.*,
                   u.first_name, u.last_name, u.email,
                   s.title AS scholarship_title,
                   {$dateCol} AS disbursement_date
            FROM disbursements d
            JOIN users u ON d.user_id = u.id
            LEFT JOIN scholarships s ON d.scholarship_id = s.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$dateCol} DESC, d.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch a single disbursement with full joined data.
 */
function getDisbursement(PDO $pdo, int $id): array|false {
    $stmt = $pdo->prepare("
        SELECT d.*,
               u.first_name, u.last_name, u.email,
               s.title AS scholarship_title
        FROM disbursements d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN scholarships s ON d.scholarship_id = s.id
        WHERE d.id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetch approved applications eligible for disbursement (for create form dropdown).
 */
function getEligibleAwards(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT a.id, a.user_id, s.amount AS award_amount,
               u.first_name, u.last_name,
               s.title AS scholarship_title,
               a.scholarship_id
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN scholarships s ON a.scholarship_id = s.id
        WHERE a.status = 'approved'
        ORDER BY a.reviewed_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build export dataset (same filters as getDisbursements).
 */
function getDisbursementsForExport(PDO $pdo, array $filters = []): array {
    return getDisbursements($pdo, $filters, 'admin', 0);
}

/**
 * Insert a notification for disbursement events.
 */
function createDisbursementNotification(PDO $pdo, int $userId, string $event, array $disbursement): void {
    $amount     = number_format((float)($disbursement['amount'] ?? 0), 2);
    $scholarship = $disbursement['scholarship_title'] ?? 'your scholarship';
    $appId      = (int)($disbursement['application_id'] ?? 0);

    $messages = [
        'disbursement_created'   => "A disbursement of ₱{$amount} has been created for your {$scholarship} award.",
        'disbursement_completed' => "Your disbursement of ₱{$amount} for {$scholarship} has been completed.",
    ];

    $message = $messages[$event] ?? "Your disbursement status has been updated.";
    $title   = $event === 'disbursement_completed' ? 'Disbursement Completed' : 'Disbursement Created';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_application_id, created_at)
            VALUES (:uid, :title, :msg, 'success', :app_id, NOW())
        ");
        $stmt->execute([
            ':uid'    => $userId,
            ':title'  => $title,
            ':msg'    => $message,
            ':app_id' => $appId ?: null,
        ]);
    } catch (Exception $e) {
        error_log('[DisbursementHelper] notification error: ' . $e->getMessage());
    }
}

/**
 * Allowed status transitions.
 */
function isValidDisbursementTransition(string $from, string $to): bool {
    $allowed = [
        'pending'   => ['processed'],
        'processed' => ['completed', 'failed'],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}
