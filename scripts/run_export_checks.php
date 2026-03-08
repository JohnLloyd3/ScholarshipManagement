<?php
// CLI script to generate export samples: CSV, XLSX, PDF
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

$pdo = null;
try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo "DB unavailable: " . $e->getMessage() . "\n";
    exit(1);
}

$data = getApplicationsReport($pdo);
$base = __DIR__ . '/applications_report_test';

// CSV
$csvFile = $base . '.csv';
$fp = fopen($csvFile, 'w');
if ($fp) {
    if (!empty($data)) {
        $first = (array)$data[0];
        $headers = array_keys($first);
        fputcsv($fp, $headers);
        foreach ($data as $row) {
            $row = (array)$row;
            $out = [];
            foreach ($headers as $h) $out[] = $row[$h] ?? '';
            fputcsv($fp, $out);
        }
    }
    fclose($fp);
    echo "Wrote CSV to: $csvFile\n";
} else {
    echo "Failed to write CSV to $csvFile\n";
}

// XLSX (PhpSpreadsheet)
if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    if (!empty($data)) {
        $first = (array)$data[0];
        $headers = array_keys($first);
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
    $xlsxFile = $base . '.xlsx';
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($xlsxFile);
    echo "Wrote XLSX to: $xlsxFile\n";
} else {
    echo "PhpSpreadsheet not installed; skipping XLSX.\n";
}

// PDF (dompdf)
if (class_exists('\Dompdf\\Dompdf')) {
    // Build HTML similarly to helper
    $title = 'Applications Report (Test)';
    $html = "<html><head><meta charset='utf-8'><style>body{font-family:Arial,Helvetica,sans-serif}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #ddd;text-align:left;font-size:12px}th{background:#f4f4f4}</style></head><body>";
    $html .= "<h2>" . htmlspecialchars($title) . "</h2>";
    if (empty($data)) {
        $html .= "<p>No data available.</p>";
    } else {
        $html .= "<table><thead><tr>";
        $headers = array_keys((array)$data[0]);
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= "</tr></thead><tbody>";
        foreach ($data as $row) {
            $row = (array)$row;
            $html .= '<tr>';
            foreach ($headers as $h) $html .= '<td>' . htmlspecialchars($row[$h] ?? '') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    $html .= '</body></html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $pdfFile = $base . '.pdf';
    file_put_contents($pdfFile, $dompdf->output());
    echo "Wrote PDF to: $pdfFile\n";
} else {
    echo "Dompdf not installed; skipping PDF.\n";
}

echo "Export checks complete.\n";
