<?php
/**
 * Intelligent Application Screening Helper
 * Auto-validates applications based on scholarship requirements
 */

function screenApplication($application_id, $user_id, $scholarship_id, $pdo) {
    // Fetch application and scholarship details
    $appStmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id');
    $appStmt->execute([':id' => $application_id]);
    $app = $appStmt->fetch();
    
    if (!$app) return ['valid' => false, 'message' => 'Application not found'];
    
    $schStmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id');
    $schStmt->execute([':id' => $scholarship_id]);
    $sch = $schStmt->fetch();
    
    if (!$sch) return ['valid' => false, 'message' => 'Scholarship not found'];
    
    $issues = [];
    
    // Check GPA requirement (use empty() to avoid undefined key warnings)
    if (!empty($sch['gpa_requirement'])) {
        preg_match('/\d+\.?\d*/', $app['details'] ?? '', $matches);
        $user_gpa = $matches[0] ?? ($app['gpa'] ?? 0);
        if ((float)$user_gpa < (float)$sch['gpa_requirement']) {
            $issues[] = "GPA requirement not met (Required: {$sch['gpa_requirement']}, Yours: $user_gpa)";
        }
    }
    
    // Check income requirement (guard against missing key)
    if (!empty($sch['income_requirement'])) {
        // This would require income info in the application
        // For now, we mark it as requiring review
        if (!preg_match('/income|financial/i', $app['details'] ?? '')) {
            $issues[] = "Income information not provided";
        }
    }
    
    // Check required documents
    // Check required documents. If the supplementary table is missing, fall back gracefully.
    $requiredDocs = 0;
    try {
        $docStmt = $pdo->prepare('SELECT COUNT(*) as count FROM scholarship_documents WHERE scholarship_id = :id');
        $docStmt->execute([':id' => $scholarship_id]);
        $requiredDocs = (int)$docStmt->fetchColumn();
    } catch (PDOException $e) {
        // Table might not exist in some installations; try to infer from eligibility_requirements
        try {
            $altStmt = $pdo->prepare("SELECT COUNT(*) FROM eligibility_requirements WHERE scholarship_id = :id AND requirement_type = 'documents'");
            $altStmt->execute([':id' => $scholarship_id]);
            $requiredDocs = (int)$altStmt->fetchColumn();
        } catch (Exception $ex) {
            $requiredDocs = 0;
        }
    }

    if ($requiredDocs > 0) {
        try {
            $uploadedStmt = $pdo->prepare('SELECT COUNT(*) as count FROM documents WHERE application_id = :aid');
            $uploadedStmt->execute([':aid' => $application_id]);
            $uploadedDocs = (int)$uploadedStmt->fetchColumn();
        } catch (Exception $e) {
            $uploadedDocs = 0;
        }

        if ($uploadedDocs < $requiredDocs) {
            $issues[] = "Not all required documents uploaded ($uploadedDocs/$requiredDocs)";
        }
    }
    
    // Determine status based on issues
    if (empty($issues)) {
        // Keep initial status as 'submitted' and allow staff to review later
        return ['valid' => true, 'status' => 'submitted', 'message' => 'Application passed initial screening'];
    } else {
        return ['valid' => false, 'status' => 'pending', 'message' => 'Application pending requirements: ' . implode('; ', $issues), 'issues' => $issues];
    }
}

/**
 * Log audit trail for application actions
 */
function logAuditTrail($pdo, $user_id, $action, $target_table, $target_id, $description = null) {
    try {
        $new_values = null;
        if ($description !== null) {
            $new_values = is_array($description) ? json_encode($description, JSON_UNESCAPED_UNICODE) : $description;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Ensure audit_logs table exists with correct schema
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(255) NOT NULL,
            entity_type VARCHAR(128) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            target_table VARCHAR(128) DEFAULT NULL,
            target_id INT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            old_values JSON DEFAULT NULL,
            new_values JSON DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_target (target_table, target_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        $stmt = $pdo->prepare('INSERT INTO audit_logs (
                user_id, action, entity_type, entity_id, target_table, target_id, description,
                new_value, new_values, ip, ip_address, user_agent, created_at
            ) VALUES (
                :uid, :action, :etype, :eid, :ttable, :tid, :descr,
                :nvals, :nvals_json, :ip, :ip_address, :ua, NOW()
            )');
        $stmt->execute([
            ':uid' => $user_id,
            ':action' => $action,
            ':etype' => $target_table,
            ':eid' => $target_id,
            ':ttable' => $target_table,
            ':tid' => $target_id,
            ':descr' => $new_values,
            ':nvals' => $new_values,
            ':nvals_json' => is_array($description) ? json_encode($description, JSON_UNESCAPED_UNICODE) : null,
            ':ip' => $ip,
            ':ip_address' => $ip,
            ':ua' => $ua
        ]);
    } catch (Exception $e) {
        error_log('[logAuditTrail] Failed: ' . $e->getMessage());
    }
}
