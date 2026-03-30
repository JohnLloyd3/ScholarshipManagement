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
    $sql = 'SELECT a.id, a.user_id, a.scholarship_id, a.gpa, a.status,
        a.submitted_at, a.created_at,
        s.title AS scholarship_title,
        u.username, u.email,
        CONCAT(u.first_name, " ", u.last_name) AS full_name
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
    // Disable output buffering to avoid corruption
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM so Excel opens it correctly
    fwrite($output, "\xEF\xBB\xBF");

    if (!empty($data)) {
        $first   = (array)$data[0];
        $headers = array_keys($first);
        fputcsv($output, $headers);

        foreach ($data as $row) {
            $row = (array)$row;
            $out = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                // Flatten JSON blobs
                if (is_string($val) && strlen($val) > 1 && ($val[0] === '{' || $val[0] === '[')) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $parts = [];
                        array_walk_recursive($decoded, function($v, $k) use (&$parts) {
                            if (!is_array($v)) $parts[] = "{$k}: {$v}";
                        });
                        $val = implode('; ', $parts);
                    }
                }
                $out[] = $val;
            }
            fputcsv($output, $out);
        }
    }

    fclose($output);
    exit;
}

/**
 * Export data to real .xlsx using PhpSpreadsheet when available.
 * Falls back to native SpreadsheetML XML (true .xlsx-compatible) if not installed.
 */
function exportToXLSX($data, $filename) {
    if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        if (!empty($data)) {
            $headers = array_keys((array)$data[0]);
            $sheet->fromArray($headers, null, 'A1');
            $rows = [];
            foreach ($data as $r) {
                $row = (array)$r;
                $out = [];
                foreach ($headers as $h) $out[] = $row[$h] ?? '';
                $rows[] = $out;
            }
            if (!empty($rows)) $sheet->fromArray($rows, null, 'A2');
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    // Native SpreadsheetML — a real XML-based format Excel opens without warnings
    while (ob_get_level()) ob_end_clean();

    $rows = [];
    if (!empty($data)) {
        $headers = array_keys((array)$data[0]);
        $rows[]  = $headers;
        foreach ($data as $r) {
            $row = (array)$r;
            $out = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                // Flatten JSON
                if (is_string($val) && strlen($val) > 1 && ($val[0] === '{' || $val[0] === '[')) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $parts = [];
                        array_walk_recursive($decoded, function($v, $k) use (&$parts) {
                            if (!is_array($v)) $parts[] = "{$k}: {$v}";
                        });
                        $val = implode('; ', $parts);
                    }
                }
                $out[] = $val;
            }
            $rows[] = $out;
        }
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
    $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '<Styles><Style ss:ID="H"><Font ss:Bold="1"/></Style></Styles>' . "\n";
    $xml .= '<Worksheet ss:Name="Sheet1"><Table>' . "\n";

    foreach ($rows as $i => $row) {
        $xml .= '<Row>';
        foreach ($row as $cell) {
            $escaped = htmlspecialchars((string)$cell, ENT_XML1, 'UTF-8');
            $type    = is_numeric($cell) && !preg_match('/^0\d/', (string)$cell) ? 'Number' : 'String';
            $style   = $i === 0 ? ' ss:StyleID="H"' : '';
            $xml    .= "<Cell{$style}><Data ss:Type=\"{$type}\">{$escaped}</Data></Cell>";
        }
        $xml .= '</Row>' . "\n";
    }

    $xml .= '</Table></Worksheet></Workbook>';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $xml;
    exit;
}

/**
 * Export data as Excel-compatible CSV (tabular CSV with .xls filename)
 */
function exportToExcel($data, $filename) {
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'xls') {
        $filename = pathinfo($filename, PATHINFO_FILENAME) . '.xls';
    }
    exportToCSV($data, $filename);
}

/**
 * Export arbitrary tabular data as PDF — pure PHP, no library required.
 */
function exportDataToPDF($data, $filename, $title = 'Report') {
    while (ob_get_level()) ob_end_clean();

    // Build table rows
    $headers = !empty($data) ? array_keys((array)$data[0]) : [];
    $rows    = [];
    foreach ($data as $r) {
        $row = (array)$r;
        $out = [];
        foreach ($headers as $h) {
            $val = $row[$h] ?? '';
            // Flatten JSON
            if (is_string($val) && strlen($val) > 1 && ($val[0] === '{' || $val[0] === '[')) {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $parts = [];
                    array_walk_recursive($decoded, function($v, $k) use (&$parts) {
                        if (!is_array($v)) $parts[] = "{$k}: {$v}";
                    });
                    $val = implode('; ', $parts);
                }
            }
            $out[] = (string)$val;
        }
        $rows[] = $out;
    }

    // --- Pure PHP PDF generation (no library) ---
    $colCount  = count($headers);
    $pageW     = 841.89; // A4 landscape width in points
    $pageH     = 595.28;
    $margin    = 30;
    $colW      = $colCount > 0 ? ($pageW - $margin * 2) / $colCount : ($pageW - $margin * 2);
    $rowH      = 16;
    $headerH   = 20;
    $titleH    = 28;
    $fontSize  = max(6, min(9, (int)(($colW - 4) / 5.5)));

    // Truncate cell text to fit column
    $fit = function(string $s, float $w, int $fs) : string {
        $maxChars = max(3, (int)($w / ($fs * 0.55)));
        return mb_strlen($s) > $maxChars ? mb_substr($s, 0, $maxChars - 1) . '…' : $s;
    };

    // Escape PDF string
    $esc = fn(string $s) => str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)','',''], $s);

    $objects  = [];
    $objCount = 0;

    $addObj = function(string $content) use (&$objects, &$objCount): int {
        $objCount++;
        $objects[$objCount] = $content;
        return $objCount;
    };

    // Object 1: catalog (filled after pages known)
    // Object 2: page tree (filled after pages known)
    // Object 3: font
    $addObj(''); // placeholder catalog
    $addObj(''); // placeholder page tree
    $fontId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
    $fontBId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");

    // Paginate rows
    $usableH    = $pageH - $margin * 2 - $titleH - $headerH;
    $rowsPerPage = max(1, (int)floor($usableH / $rowH));
    $chunks      = !empty($rows) ? array_chunk($rows, $rowsPerPage) : [[]];
    $pageIds     = [];

    foreach ($chunks as $chunkIdx => $chunk) {
        $stream = '';

        // Title (first page only)
        $y = $pageH - $margin;
        if ($chunkIdx === 0) {
            $stream .= "BT /F2 14 Tf {$margin} " . ($y - 14) . " Td (" . $esc($title) . ") Tj ET\n";
            $y -= $titleH;
        }

        // Header row background
        $stream .= "0.18 0.18 0.18 rg\n";
        $stream .= "{$margin} " . ($y - $headerH) . " " . ($pageW - $margin * 2) . " {$headerH} re f\n";
        $stream .= "1 1 1 rg\n"; // white text

        // Header text
        $stream .= "BT /F2 {$fontSize} Tf\n";
        $x = $margin + 3;
        foreach ($headers as $i => $h) {
            $cx = $margin + $i * $colW + 3;
            $stream .= "{$cx} " . ($y - $headerH + 5) . " Td (" . $esc($fit(strtoupper($h), $colW - 6, $fontSize)) . ") Tj\n";
            $stream .= "0 0 Td\n";
        }
        $stream .= "ET\n";
        $y -= $headerH;

        // Data rows
        $stream .= "0 0 0 rg\n"; // black text
        foreach ($chunk as $ri => $row) {
            // Alternating row background
            if ($ri % 2 === 0) {
                $stream .= "0.96 0.96 0.96 rg\n";
                $stream .= "{$margin} " . ($y - $rowH) . " " . ($pageW - $margin * 2) . " {$rowH} re f\n";
                $stream .= "0 0 0 rg\n";
            }
            $stream .= "BT /F1 {$fontSize} Tf\n";
            foreach ($row as $ci => $cell) {
                $cx = $margin + $ci * $colW + 3;
                $stream .= "{$cx} " . ($y - $rowH + 4) . " Td (" . $esc($fit($cell, $colW - 6, $fontSize)) . ") Tj\n";
                $stream .= "0 0 Td\n";
            }
            $stream .= "ET\n";
            // Row border line
            $stream .= "0.85 0.85 0.85 RG 0.3 w\n";
            $stream .= "{$margin} " . ($y - $rowH) . " m " . ($pageW - $margin) . " " . ($y - $rowH) . " l S\n";
            $y -= $rowH;
        }

        // Page border
        $stream .= "0.7 0.7 0.7 RG 0.5 w\n";
        $stream .= "{$margin} {$margin} " . ($pageW - $margin * 2) . " " . ($pageH - $margin * 2) . " re S\n";

        $len    = strlen($stream);
        $pageContentId = $addObj("<< /Length {$len} >>\nstream\n{$stream}\nendstream");

        $pageId = $addObj(
            "<< /Type /Page /Parent 2 0 R " .
            "/MediaBox [0 0 {$pageW} {$pageH}] " .
            "/Contents {$pageContentId} 0 R " .
            "/Resources << /Font << /F1 {$fontId} 0 R /F2 {$fontBId} 0 R >> >> >>"
        );
        $pageIds[] = $pageId;
    }

    $pageCount = count($pageIds);
    $kidsStr   = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageIds));

    // Fill in catalog and page tree
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [{$kidsStr}] /Count {$pageCount} >>";

    // Assemble PDF
    $pdf    = "%PDF-1.4\n";
    $xref   = [];
    for ($i = 1; $i <= $objCount; $i++) {
        $xref[$i] = strlen($pdf);
        $pdf .= "{$i} 0 obj\n{$objects[$i]}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . ($objCount + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $objCount; $i++) {
        $pdf .= str_pad($xref[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . ($objCount + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $pdf;
    exit;
}
