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
    // Select only existing columns from applications schema and useful related info
    $sql = 'SELECT a.id, a.user_id, a.scholarship_id, a.motivational_letter AS details, a.gpa, a.status, a.submitted_at, a.created_at,
        s.title AS scholarship_title, u.username, u.email
        FROM applications a
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
        // Normalize rows to associative arrays
        $first = (array)$data[0];
        $headers = array_keys($first);
        fputcsv($output, $headers);

        foreach ($data as $row) {
            $row = (array)$row;
            $out = [];
            foreach ($headers as $h) $out[] = $row[$h] ?? '';
            fputcsv($output, $out);
        }
    }

    fclose($output);
    exit;
}

/**
 * Export data to real .xlsx using PhpSpreadsheet when available.
 * Falls back to CSV-based Excel export if PhpSpreadsheet is not installed.
 */
function exportToXLSX($data, $filename) {
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // fallback: use CSV but name .xls/.xlsx accordingly
        if (pathinfo($filename, PATHINFO_EXTENSION) !== 'xls' && pathinfo($filename, PATHINFO_EXTENSION) !== 'xlsx') {
            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.xls';
        }
        exportToExcel($data, $filename);
    }

    // Use PhpSpreadsheet to build an actual XLSX
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    if (!empty($data)) {
        $first = (array)$data[0];
        $headers = array_keys($first);

        // Write headers to first row
        $sheet->fromArray($headers, null, 'A1');

        // Prepare rows matching header order
        $rows = [];
        foreach ($data as $r) {
            $row = (array)$r;
            $out = [];
            foreach ($headers as $h) $out[] = $row[$h] ?? '';
            $rows[] = $out;
        }

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }
    }

    // Stream to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
}

/**
 * Export data as Excel-compatible CSV (tabular CSV with .xls filename)
 */
function exportToExcel($data, $filename) {
    // Reuse CSV exporter but set filename to .xls for Excel compatibility
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'xls') {
        $filename = pathinfo($filename, PATHINFO_FILENAME) . '.xls';
    }
    exportToCSV($data, $filename);
}

/**
 * Export arbitrary tabular data as PDF using dompdf if available.
 * If dompdf is not installed, send an informative message.
 */
function exportDataToPDF($data, $filename, $title = 'Report') {
    if (!class_exists('\Dompdf\\Dompdf')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "PDF export requires dompdf.\n";
        echo "Install via Composer: composer require dompdf/dompdf\n";
        exit;
    }

    // Build simple HTML table
    $html = "<html><head><meta charset='utf-8'><style>body{font-family:Arial,Helvetica,sans-serif}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #ddd;text-align:left;font-size:12px}th{background:#f4f4f4}</style></head><body>";
    $html .= "<h2>" . htmlspecialchars($title) . "</h2>";
    if (empty($data)) {
        $html .= "<p>No data available.</p>";
    } else {
        $html .= "<table><thead><tr>";
        // headers from first row
        $headers = array_keys((array)$data[0]);
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= "</tr></thead><tbody>";
        foreach ($data as $row) {
            $row = (array)$row;
            $html .= '<tr>';
            foreach ($headers as $h) {
                $html .= '<td>' . htmlspecialchars($row[$h] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}
