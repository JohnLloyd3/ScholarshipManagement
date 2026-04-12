<?php
/**
 * Fraud Detection Helper
 * Detects suspicious activities and potential fraud
 */

/**
 * Run fraud detection checks on an application
 */
function runFraudDetection($pdo, $applicationId) {
    $alerts = [];
    
    // Get application details
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id');
    $stmt->execute([':id' => $applicationId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) return ['alerts' => [], 'fraud_score' => 0];
    
    // Check 1: Multiple applications from same student for same scholarship
    $alerts = array_merge($alerts, detectDuplicateApplications($pdo, $app));
    
    // Check 2: Duplicate documents (same file hash)
    $alerts = array_merge($alerts, detectDuplicateDocuments($pdo, $app));
    
    // Check 3: Suspicious income data
    $alerts = array_merge($alerts, detectSuspiciousIncome($pdo, $app));
    
    // Check 4: Multiple accounts with same personal info
    $alerts = array_merge($alerts, detectMultipleAccounts($pdo, $app));
    
    // Calculate fraud score
    $fraudScore = calculateFraudScore($alerts);
    
    // Update application fraud score
    $updateStmt = $pdo->prepare('UPDATE applications SET fraud_score = :score, fraud_checked_at = NOW() WHERE id = :id');
    $updateStmt->execute([':score' => $fraudScore, ':id' => $applicationId]);
    
    return [
        'alerts' => $alerts,
        'fraud_score' => $fraudScore
    ];
}

/**
 * Detect duplicate applications from same student
 */
function detectDuplicateApplications($pdo, $app) {
    $alerts = [];
    
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count, GROUP_CONCAT(id) as app_ids
        FROM applications 
        WHERE user_id = :user_id 
        AND scholarship_id = :scholarship_id 
        AND id != :current_id
        AND status NOT IN ("withdrawn", "draft")
    ');
    $stmt->execute([
        ':user_id' => $app['user_id'],
        ':scholarship_id' => $app['scholarship_id'],
        ':current_id' => $app['id']
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $alerts[] = createFraudAlert(
            $pdo,
            'duplicate_application',
            'high',
            $app['user_id'],
            $app['id'],
            null,
            'Student has ' . $result['count'] . ' other application(s) for the same scholarship',
            ['duplicate_app_ids' => $result['app_ids']]
        );
    }
    
    return $alerts;
}

/**
 * Detect duplicate documents (same file hash)
 */
function detectDuplicateDocuments($pdo, $app) {
    $alerts = [];
    
    // Get documents for this application
    $stmt = $pdo->prepare('SELECT * FROM documents WHERE application_id = :app_id AND file_hash IS NOT NULL');
    $stmt->execute([':app_id' => $app['id']]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($docs as $doc) {
        // Check if this hash exists in other applications
        $dupStmt = $pdo->prepare('
            SELECT d.*, a.user_id, a.id as app_id
            FROM documents d
            JOIN applications a ON d.application_id = a.id
            WHERE d.file_hash = :hash 
            AND d.id != :doc_id
            AND a.user_id != :user_id
            LIMIT 5
        ');
        $dupStmt->execute([
            ':hash' => $doc['file_hash'],
            ':doc_id' => $doc['id'],
            ':user_id' => $app['user_id']
        ]);
        $duplicates = $dupStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($duplicates) > 0) {
            $alerts[] = createFraudAlert(
                $pdo,
                'duplicate_document',
                'critical',
                $app['user_id'],
                $app['id'],
                $doc['id'],
                'Document "' . $doc['file_name'] . '" matches ' . count($duplicates) . ' document(s) from other applicants',
                ['duplicate_docs' => array_column($duplicates, 'id')]
            );
        }
    }
    
    return $alerts;
}

/**
 * Detect suspicious income data
 */
function detectSuspiciousIncome($pdo, $app) {
    $alerts = [];
    
    $income = floatval($app['family_income'] ?? 0);
    
    if ($income <= 0) {
        return $alerts; // No income data to check
    }
    
    // Check 1: Income is exactly the same as other applications (suspicious pattern)
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count, GROUP_CONCAT(id) as app_ids
        FROM applications 
        WHERE family_income = :income 
        AND id != :current_id
        AND user_id != :user_id
        HAVING count >= 3
    ');
    $stmt->execute([
        ':income' => $income,
        ':current_id' => $app['id'],
        ':user_id' => $app['user_id']
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['count'] >= 3) {
        $alerts[] = createFraudAlert(
            $pdo,
            'suspicious_income',
            'medium',
            $app['user_id'],
            $app['id'],
            null,
            'Income amount (' . number_format($income, 2) . ') matches ' . $result['count'] . ' other applications exactly',
            ['matching_apps' => $result['app_ids']]
        );
    }
    
    // Check 2: Round numbers that are suspiciously common (e.g., exactly 10000, 20000)
    if ($income > 0 && $income == floor($income / 10000) * 10000 && $income >= 10000) {
        $alerts[] = createFraudAlert(
            $pdo,
            'suspicious_income',
            'low',
            $app['user_id'],
            $app['id'],
            null,
            'Income is a suspiciously round number: ' . number_format($income, 2),
            ['income' => $income]
        );
    }
    
    return $alerts;
}

/**
 * Detect multiple accounts with same personal information
 */
function detectMultipleAccounts($pdo, $app) {
    $alerts = [];
    
    // Get user details
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $app['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) return $alerts;
    
    // Check for same email domain + similar names
    $emailParts = explode('@', $user['email']);
    if (count($emailParts) == 2) {
        $domain = $emailParts[1];
        
        // Check for multiple accounts with same domain and similar first/last name
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count, GROUP_CONCAT(id) as user_ids
            FROM users 
            WHERE email LIKE :domain
            AND id != :user_id
            AND (first_name = :first_name OR last_name = :last_name)
            AND role = "student"
        ');
        $stmt->execute([
            ':domain' => '%@' . $domain,
            ':user_id' => $user['id'],
            ':first_name' => $user['first_name'],
            ':last_name' => $user['last_name']
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $alerts[] = createFraudAlert(
                $pdo,
                'multiple_accounts',
                'medium',
                $app['user_id'],
                $app['id'],
                null,
                'Found ' . $result['count'] . ' other account(s) with similar name and email domain',
                ['related_users' => $result['user_ids']]
            );
        }
    }
    
    return $alerts;
}

/**
 * Create a fraud alert
 */
function createFraudAlert($pdo, $type, $severity, $userId, $appId, $docId, $description, $evidence) {
    // Check if similar alert already exists
    $checkStmt = $pdo->prepare('
        SELECT id FROM fraud_alerts 
        WHERE alert_type = :type 
        AND user_id = :user_id 
        AND application_id = :app_id
        AND status = "pending"
        LIMIT 1
    ');
    $checkStmt->execute([
        ':type' => $type,
        ':user_id' => $userId,
        ':app_id' => $appId
    ]);
    
    if ($checkStmt->fetch()) {
        // Alert already exists — still return severity so score is counted
        return ['severity' => $severity, 'type' => $type, 'existing' => true];
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO fraud_alerts 
        (alert_type, severity, user_id, application_id, document_id, description, evidence, created_at)
        VALUES (:type, :severity, :user_id, :app_id, :doc_id, :description, :evidence, NOW())
    ');
    
    $stmt->execute([
        ':type' => $type,
        ':severity' => $severity,
        ':user_id' => $userId,
        ':app_id' => $appId,
        ':doc_id' => $docId,
        ':description' => $description,
        ':evidence' => json_encode($evidence)
    ]);
    
    return ['severity' => $severity, 'type' => $type, 'id' => (int)$pdo->lastInsertId()];
}

/**
 * Calculate fraud score based on alerts
 */
function calculateFraudScore($alerts) {
    $score = 0;
    
    $weights = [
        'critical' => 40,
        'high'     => 25,
        'medium'   => 15,
        'low'      => 5
    ];
    
    foreach ($alerts as $alert) {
        if (is_array($alert) && isset($alert['severity'])) {
            $score += $weights[$alert['severity']] ?? 0;
        }
        // skip nulls or ints (legacy)
    }
    
    return min($score, 100); // Cap at 100
}

/**
 * Calculate file hash for duplicate detection
 */
function calculateFileHash($filePath) {
    if (!file_exists($filePath)) {
        return null;
    }
    return hash_file('sha256', $filePath);
}

/**
 * Get fraud statistics
 */
function getFraudStatistics($pdo) {
    $stats = [];
    
    // Total alerts by status
    $stmt = $pdo->query('
        SELECT status, COUNT(*) as count 
        FROM fraud_alerts 
        GROUP BY status
    ');
    $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total alerts by type
    $stmt = $pdo->query('
        SELECT alert_type, COUNT(*) as count 
        FROM fraud_alerts 
        GROUP BY alert_type
    ');
    $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total alerts by severity
    $stmt = $pdo->query('
        SELECT severity, COUNT(*) as count 
        FROM fraud_alerts 
        GROUP BY severity
    ');
    $stats['by_severity'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // High-risk applications (fraud score > 50)
    $stmt = $pdo->query('
        SELECT COUNT(*) as count 
        FROM applications 
        WHERE fraud_score > 50
    ');
    $stats['high_risk_apps'] = $stmt->fetchColumn();
    
    return $stats;
}
