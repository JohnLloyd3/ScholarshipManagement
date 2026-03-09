<?php
/**
 * Audit Logging Helper
 * Comprehensive activity tracking for security and compliance
 */

/**
 * Log an audit trail entry
 * 
 * @param PDO $pdo Database connection
 * @param int|null $user_id User performing the action
 * @param string $action Action performed (e.g., 'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'EXPORT')
 * @param string|null $entity_type Type of entity (e.g., 'user', 'scholarship', 'application')
 * @param int|null $entity_id ID of the entity
 * @param string|null $old_value Previous value (for updates)
 * @param string|null $new_value New value (for updates)
 * @return bool Success status
 */
function logAudit($pdo, $user_id, $action, $entity_type = null, $entity_id = null, $old_value = null, $new_value = null) {
    try {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(255) NOT NULL,
            entity_type VARCHAR(128) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip, user_agent, created_at) 
                               VALUES (:uid, :action, :etype, :eid, :old, :new, :ip, :ua, NOW())');
        
        return $stmt->execute([
            ':uid' => $user_id,
            ':action' => $action,
            ':etype' => $entity_type,
            ':eid' => $entity_id,
            ':old' => $old_value ? (is_array($old_value) ? json_encode($old_value) : $old_value) : null,
            ':new' => $new_value ? (is_array($new_value) ? json_encode($new_value) : $new_value) : null,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('[AuditHelper] Failed to log audit: ' . $e->getMessage());
        return false;
    }
}

/**
 * Shorthand for common audit actions
 */
function logUserCreated($pdo, $admin_id, $new_user_id, $username) {
    return logAudit($pdo, $admin_id, 'USER_CREATED', 'user', $new_user_id, null, $username);
}

function logUserUpdated($pdo, $admin_id, $user_id, $old_data, $new_data) {
    return logAudit($pdo, $admin_id, 'USER_UPDATED', 'user', $user_id, json_encode($old_data), json_encode($new_data));
}

function logUserDeleted($pdo, $admin_id, $user_id, $username) {
    return logAudit($pdo, $admin_id, 'USER_DELETED', 'user', $user_id, $username, null);
}

function logLogin($pdo, $user_id, $username) {
    return logAudit($pdo, $user_id, 'LOGIN', 'user', $user_id, null, $username);
}

function logLogout($pdo, $user_id) {
    return logAudit($pdo, $user_id, 'LOGOUT', 'user', $user_id, null, null);
}

function logScholarshipCreated($pdo, $user_id, $scholarship_id, $title) {
    return logAudit($pdo, $user_id, 'SCHOLARSHIP_CREATED', 'scholarship', $scholarship_id, null, $title);
}

function logScholarshipUpdated($pdo, $user_id, $scholarship_id, $old_data, $new_data) {
    return logAudit($pdo, $user_id, 'SCHOLARSHIP_UPDATED', 'scholarship', $scholarship_id, json_encode($old_data), json_encode($new_data));
}

function logScholarshipDeleted($pdo, $user_id, $scholarship_id, $title) {
    return logAudit($pdo, $user_id, 'SCHOLARSHIP_DELETED', 'scholarship', $scholarship_id, $title, null);
}

function logApplicationSubmitted($pdo, $user_id, $application_id, $scholarship_id) {
    return logAudit($pdo, $user_id, 'APPLICATION_SUBMITTED', 'application', $application_id, null, 'Scholarship #' . $scholarship_id);
}

function logApplicationStatusChanged($pdo, $user_id, $application_id, $old_status, $new_status) {
    return logAudit($pdo, $user_id, 'APPLICATION_STATUS_CHANGED', 'application', $application_id, $old_status, $new_status);
}

function logDocumentUploaded($pdo, $user_id, $document_id, $filename) {
    return logAudit($pdo, $user_id, 'DOCUMENT_UPLOADED', 'document', $document_id, null, $filename);
}

function logDocumentVerified($pdo, $user_id, $document_id, $status) {
    return logAudit($pdo, $user_id, 'DOCUMENT_VERIFIED', 'document', $document_id, null, $status);
}

function logExport($pdo, $user_id, $export_type, $format) {
    return logAudit($pdo, $user_id, 'EXPORT', $export_type, null, null, $format);
}

function logPasswordReset($pdo, $user_id, $email) {
    return logAudit($pdo, $user_id, 'PASSWORD_RESET', 'user', $user_id, null, $email);
}

function logAnnouncementCreated($pdo, $user_id, $announcement_id, $title) {
    return logAudit($pdo, $user_id, 'ANNOUNCEMENT_CREATED', 'announcement', $announcement_id, null, $title);
}

function logSettingsChanged($pdo, $user_id, $setting_name, $old_value, $new_value) {
    return logAudit($pdo, $user_id, 'SETTINGS_CHANGED', 'settings', null, $old_value, $new_value);
}

/**
 * Get recent audit logs
 */
function getRecentAuditLogs($pdo, $limit = 50) {
    $stmt = $pdo->prepare('SELECT a.*, u.username, u.email FROM audit_logs a 
                           LEFT JOIN users u ON a.user_id = u.id 
                           ORDER BY a.created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get audit logs for specific user
 */
function getUserAuditLogs($pdo, $user_id, $limit = 100) {
    $stmt = $pdo->prepare('SELECT * FROM audit_logs WHERE user_id = :uid ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':uid', (int)$user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get audit logs for specific entity
 */
function getEntityAuditLogs($pdo, $entity_type, $entity_id, $limit = 50) {
    $stmt = $pdo->prepare('SELECT a.*, u.username FROM audit_logs a 
                           LEFT JOIN users u ON a.user_id = u.id 
                           WHERE a.entity_type = :etype AND a.entity_id = :eid 
                           ORDER BY a.created_at DESC LIMIT :limit');
    $stmt->bindValue(':etype', $entity_type, PDO::PARAM_STR);
    $stmt->bindValue(':eid', (int)$entity_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Clean old audit logs (for maintenance)
 */
function cleanOldAuditLogs($pdo, $days = 365) {
    $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
    $stmt->execute([':days' => (int)$days]);
    return $stmt->rowCount();
}
