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
    // Initialize
    $where = ['1=1'];
    $params = [];
    
    // Check column existence
    $hasDeletedAt = false;
    $hasDate = false;
    $hasScholarshipId = false;
    $hasApplicationId = false;
    
    try {
        $hasDeletedAt = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'deleted_at'")->fetch();
        $hasDate = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'disbursement_date'")->fetch();
        $hasScholarshipId = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'scholarship_id'")->fetch();
        $hasApplicationId = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'application_id'")->fetch();
    } catch (Exception $e) {
        // Ignore column check errors
    }
    
    // Set date column
    $dateCol = $hasDate ? 'd.disbursement_date' : 'd.created_at';
    
    // Build joins
    if ($hasScholarshipId) {
        $joins = "JOIN users u ON d.user_id = u.id
            LEFT JOIN scholarships s ON d.scholarship_id = s.id";
        $scholarshipSelect = 's.title AS scholarship_title';
    } elseif ($hasApplicationId) {
        $joins = "JOIN users u ON d.user_id = u.id
            LEFT JOIN applications a ON d.application_id = a.id
            LEFT JOIN scholarships s ON s.id = a.scholarship_id";
        $scholarshipSelect = 's.title AS scholarship_title';
    } else {
        $joins = "JOIN users u ON d.user_id = u.id";
        $scholarshipSelect = 'NULL AS scholarship_title';
    }
    
    // Apply WHERE conditions
    if ($hasDeletedAt) {
        $where[] = 'd.deleted_at IS NULL';
    }
    
    if ($role === 'student') {
        $where[] = 'd.user_id = :uid';
        $params[':uid'] = $userId;
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        $where[] = 'd.status = :status';
        $params[':status'] = $filters['status'];
    }
    
    // Date from filter
    if (!empty($filters['date_from'])) {
        $where[] = "{$dateCol} >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    // Date to filter
    if (!empty($filters['date_to'])) {
        $where[] = "{$dateCol} <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    // Student search filter
    if (!empty($filters['student'])) {
        $where[] = "(u.first_name LIKE :student OR u.last_name LIKE :student OR CONCAT(u.first_name,' ',u.last_name) LIKE :student OR u.student_id LIKE :student OR u.email LIKE :student)";
        $params[':student'] = '%' . $filters['student'] . '%';
    }
    
    // Scholarship filter
    if (!empty($filters['scholarship'])) {
        if ($hasScholarshipId) {
            $where[] = 'd.scholarship_id = :scholarship';
            $params[':scholarship'] = (int)$filters['scholarship'];
        } elseif ($hasApplicationId) {
            $where[] = 'a.scholarship_id = :scholarship';
            $params[':scholarship'] = (int)$filters['scholarship'];
        }
    }
    
    // Build final SQL
    $sql = "SELECT d.*,
                   u.first_name, u.last_name, u.email, u.student_id,
                   {$scholarshipSelect},
                   {$dateCol} AS disbursement_date
            FROM disbursements d
            {$joins}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$dateCol} DESC, d.id DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("DisbursementHelper Error: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . json_encode($params));
        // Return empty array instead of throwing
        return [];
    }
}

/**
 * Fetch a single disbursement with full joined data.
 */
function getDisbursement(PDO $pdo, int $id): array|false {
    // Check column existence
    $hasScholarshipId = false;
    $hasApplicationId = false;
    
    try {
        $hasScholarshipId = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'scholarship_id'")->fetch();
        $hasApplicationId = (bool)$pdo->query("SHOW COLUMNS FROM `disbursements` LIKE 'application_id'")->fetch();
    } catch (Exception $e) {
        // Ignore
    }

    if ($hasScholarshipId) {
        $sql = "SELECT d.*,
                       u.first_name, u.last_name, u.email, u.student_id,
                       s.title AS scholarship_title
                FROM disbursements d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN scholarships s ON d.scholarship_id = s.id
                WHERE d.id = :id";
    } elseif ($hasApplicationId) {
        $sql = "SELECT d.*,
                       u.first_name, u.last_name, u.email, u.student_id,
                       s.title AS scholarship_title
                FROM disbursements d
                JOIN users u ON d.user_id = u.id
                LEFT JOIN applications a ON d.application_id = a.id
                LEFT JOIN scholarships s ON s.id = a.scholarship_id
                WHERE d.id = :id";
    } else {
        $sql = "SELECT d.*,
                       u.first_name, u.last_name, u.email, u.student_id,
                       NULL AS scholarship_title
                FROM disbursements d
                JOIN users u ON d.user_id = u.id
                WHERE d.id = :id";
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getDisbursement Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch approved applications eligible for disbursement (for create form dropdown).
 * Excludes applications that already have disbursement records.
 * Returns only ONE (the latest) application per person.
 */
function getEligibleApplications(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT a.id, a.user_id, s.amount AS application_amount,
                   u.first_name, u.last_name, u.student_id,
                   s.title AS scholarship_title,
                   a.scholarship_id,
                   a.reviewed_at
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN scholarships s ON a.scholarship_id = s.id
            WHERE a.status = 'approved'
            AND NOT EXISTS (
                SELECT 1 FROM disbursements d 
                WHERE d.user_id = a.user_id 
                AND d.scholarship_id = a.scholarship_id
                AND (d.deleted_at IS NULL OR d.deleted_at = '0000-00-00 00:00:00')
            )
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getEligibleApplications Error: " . $e->getMessage());
        return [];
    }
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
    $amount      = number_format((float)($disbursement['amount'] ?? 0), 2);
    $scholarship = $disbursement['scholarship_title'] ?? 'your scholarship';
    $appId       = (int)($disbursement['application_id'] ?? 0);

    $messages = [
        'disbursement_created'   => "A cash disbursement of ₱{$amount} has been created for your {$scholarship} scholarship.",
        'disbursement_completed' => "Your cash disbursement of ₱{$amount} for {$scholarship} has been completed. Please collect your payment.",
    ];

    $message = $messages[$event] ?? "Your disbursement status has been updated.";
    $title   = $event === 'disbursement_completed' ? 'Disbursement Completed' : 'Disbursement Created';

    if (!function_exists('notifyStudent')) {
        require_once __DIR__ . '/NotificationHelper.php';
    }
    
    try {
        notifyStudent($pdo, $userId, $title, $message, 'success', $appId ?: null);
    } catch (Exception $e) {
        error_log("createDisbursementNotification Error: " . $e->getMessage());
    }
}

/**
 * Allowed status transitions.
 */
function isValidDisbursementTransition(string $from, string $to): bool {
    $allowed = [
        'pending'    => ['processing'],
        'processing' => ['completed', 'failed'],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}

/**
 * Calculate the next available disbursement date based on 300 people per day limit.
 * Returns the date when this person can be scheduled, organized alphabetically.
 */
function getNextAvailableDisbursementDate(PDO $pdo, string $firstName, string $lastName): string {
    try {
        // Start from today
        $checkDate = date('Y-m-d');
        $maxAttempts = 365; // Check up to 1 year ahead
        $dailyLimit = 300;
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Count how many people already scheduled for this date
            $countStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT d.user_id) as person_count
                FROM disbursements d
                WHERE DATE(d.disbursement_date) = :date
                AND (d.deleted_at IS NULL OR d.deleted_at = '0000-00-00 00:00:00')
            ");
            $countStmt->execute([':date' => $checkDate]);
            $personCount = (int)$countStmt->fetchColumn();
            
            if ($personCount < $dailyLimit) {
                // This date has space available
                // Now check alphabetical position
                $alphabeticalStmt = $pdo->prepare("
                    SELECT u.first_name, u.last_name
                    FROM disbursements d
                    JOIN users u ON d.user_id = u.id
                    WHERE DATE(d.disbursement_date) = :date
                    AND (d.deleted_at IS NULL OR d.deleted_at = '0000-00-00 00:00:00')
                    ORDER BY u.last_name ASC, u.first_name ASC
                ");
                $alphabeticalStmt->execute([':date' => $checkDate]);
                $scheduledPeople = $alphabeticalStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Find where this person fits alphabetically
                $fullName = $lastName . ' ' . $firstName;
                $position = 0;
                foreach ($scheduledPeople as $person) {
                    $scheduledName = $person['last_name'] . ' ' . $person['first_name'];
                    if (strcasecmp($fullName, $scheduledName) > 0) {
                        $position++;
                    }
                }
                
                // If position is within limit, this date works
                if ($position < $dailyLimit) {
                    return $checkDate;
                }
            }
            
            // Move to next day
            $checkDate = date('Y-m-d', strtotime($checkDate . ' +1 day'));
        }
        
        // Fallback: return today if no date found
        return date('Y-m-d');
        
    } catch (Exception $e) {
        error_log('[getNextAvailableDisbursementDate] Error: ' . $e->getMessage());
        return date('Y-m-d');
    }
}

/**
 * Get disbursement queue statistics for display.
 */
function getDisbursementQueueStats(PDO $pdo): array {
    try {
        // Count people scheduled for today
        $todayStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT d.user_id) as count
            FROM disbursements d
            WHERE DATE(d.disbursement_date) = CURDATE()
            AND (d.deleted_at IS NULL OR d.deleted_at = '0000-00-00 00:00:00')
        ");
        $todayStmt->execute();
        $todayCount = (int)$todayStmt->fetchColumn();
        
        // Count total pending people
        $pendingStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT d.user_id) as count
            FROM disbursements d
            WHERE d.status IN ('pending', 'processing')
            AND (d.deleted_at IS NULL OR d.deleted_at = '0000-00-00 00:00:00')
        ");
        $pendingStmt->execute();
        $pendingCount = (int)$pendingStmt->fetchColumn();
        
        return [
            'today_count' => $todayCount,
            'today_remaining' => max(0, 300 - $todayCount),
            'pending_count' => $pendingCount,
        ];
    } catch (Exception $e) {
        error_log('[getDisbursementQueueStats] Error: ' . $e->getMessage());
        return [
            'today_count' => 0,
            'today_remaining' => 300,
            'pending_count' => 0,
        ];
    }
}
