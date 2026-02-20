<?php
/**
 * Analytics & Reporting Helper for Admin Dashboard
 * Provides statistics, charts data, and reports
 */

/**
 * Get dashboard statistics
 */
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total applications
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM applications');
    $stats['total_applications'] = $stmt->fetchColumn();
    
    // Applications by status
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM applications GROUP BY status');
    $stats['applications_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Approved vs Rejected
    $stmt = $pdo->query('SELECT 
        SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
    FROM applications');
    $result = $stmt->fetch();
    $stats['approved_count'] = $result['approved'] ?? 0;
    $stats['rejected_count'] = $result['rejected'] ?? 0;
    
    // Most applied scholarship
    $stmt = $pdo->query('SELECT s.title, COUNT(a.id) as count FROM scholarships s
        LEFT JOIN applications a ON a.scholarship_id = s.id
        GROUP BY s.id ORDER BY count DESC LIMIT 5');
    $stats['top_scholarships'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total users by role
    $stmt = $pdo->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
    $stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total scholarships
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM scholarships');
    $stats['total_scholarships'] = $stmt->fetchColumn();
    
    // Open scholarships
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM scholarships WHERE status = "open"');
    $stats['open_scholarships'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Get applications report with filters
 */
function getApplicationsReport($pdo, $filters = []) {
    $sql = 'SELECT a.id, a.user_id, a.scholarship_id, a.title, a.status, a.created_at, a.academic_year,
            s.title as scholarship_title, u.username, u.email FROM applications a
            LEFT JOIN scholarships s ON a.scholarship_id = s.id
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1';
    $params = [];
    
    if (!empty($filters['status'])) {
        $sql .= ' AND a.status = :status';
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['scholarship_id'])) {
        $sql .= ' AND a.scholarship_id = :sid';
        $params[':sid'] = $filters['scholarship_id'];
    }
    
    if (!empty($filters['academic_year'])) {
        $sql .= ' AND a.academic_year = :ay';
        $params[':ay'] = $filters['academic_year'];
    }
    
    $sql .= ' ORDER BY a.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get scholarship performance report
 */
function getScholarshipPerformance($pdo, $scholarship_id) {
    $sql = 'SELECT 
        s.id, s.title, s.organization, s.status, s.deadline, s.max_scholars,
        COUNT(a.id) as total_applications,
        SUM(CASE WHEN a.status = "approved" THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN a.status = "rejected" THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN a.status = "under_review" THEN 1 ELSE 0 END) as under_review_count
    FROM scholarships s
    LEFT JOIN applications a ON a.scholarship_id = s.id
    WHERE s.id = :id
    GROUP BY s.id';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $scholarship_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get audit log entries
 */
function getAuditLogs($pdo, $filters = [], $limit = 100) {
    $sql = 'SELECT al.id, al.user_id, al.action, al.target_table, al.target_id, al.description, al.created_at,
            u.username FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1';
    $params = [];
    
    if (!empty($filters['action'])) {
        $sql .= ' AND al.action = :action';
        $params[':action'] = $filters['action'];
    }
    
    if (!empty($filters['target_table'])) {
        $sql .= ' AND al.target_table = :table';
        $params[':table'] = $filters['target_table'];
    }
    
    $sql .= ' ORDER BY al.created_at DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
