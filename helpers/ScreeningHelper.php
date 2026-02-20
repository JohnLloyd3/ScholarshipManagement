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
    
    // Check GPA requirement
    if ($sch['gpa_requirement']) {
        preg_match('/\d+\.?\d*/', $app['details'], $matches);
        $user_gpa = $matches[0] ?? 0;
        if ((float)$user_gpa < (float)$sch['gpa_requirement']) {
            $issues[] = "GPA requirement not met (Required: {$sch['gpa_requirement']}, Yours: $user_gpa)";
        }
    }
    
    // Check income requirement
    if ($sch['income_requirement']) {
        // This would require income info in the application
        // For now, we mark it as requiring review
        if (!preg_match('/income|financial/i', $app['details'])) {
            $issues[] = "Income information not provided";
        }
    }
    
    // Check required documents
    $docStmt = $pdo->prepare('SELECT COUNT(*) as count FROM scholarship_documents WHERE scholarship_id = :id');
    $docStmt->execute([':id' => $scholarship_id]);
    $requiredDocs = $docStmt->fetchColumn();
    
    if ($requiredDocs > 0) {
        $uploadedStmt = $pdo->prepare('SELECT COUNT(*) as count FROM documents WHERE application_id = :aid');
        $uploadedStmt->execute([':aid' => $application_id]);
        $uploadedDocs = $uploadedStmt->fetchColumn();
        
        if ($uploadedDocs < $requiredDocs) {
            $issues[] = "Not all required documents uploaded ($uploadedDocs/$requiredDocs)";
        }
    }
    
    // Determine status based on issues
    if (empty($issues)) {
        return ['valid' => true, 'status' => 'under_review', 'message' => 'Application passed initial screening'];
    } else {
        return ['valid' => false, 'status' => 'pending', 'message' => 'Application pending requirements: ' . implode('; ', $issues), 'issues' => $issues];
    }
}

/**
 * Log audit trail for application actions
 */
function logAuditTrail($pdo, $user_id, $action, $target_table, $target_id, $description = null) {
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, target_table, target_id, description, created_at) VALUES (:uid, :action, :table, :tid, :desc, NOW())');
        $stmt->execute([
            ':uid' => $user_id,
            ':action' => $action,
            ':table' => $target_table,
            ':tid' => $target_id,
            ':desc' => $description
        ]);
    } catch (Exception $e) {
        // Silent fail for audit logs
    }
}
