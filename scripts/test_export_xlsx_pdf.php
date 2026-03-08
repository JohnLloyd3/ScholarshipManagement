<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

$pdo = getPDO();
$data = getApplicationsReport($pdo);

// Test XLSX
try {
    exportToXLSX($data, __DIR__ . '/applications_report_test.xlsx');
} catch (Exception $e) {
    echo "XLSX export failed: " . $e->getMessage() . "\n";
}

// Test PDF
try {
    exportDataToPDF($data, 'applications_report_test.pdf', 'Applications Report (Test)');
} catch (Exception $e) {
    echo "PDF export failed: " . $e->getMessage() . "\n";
}

echo "Done tests (XLSX streamed to browser in real use).\n";
?>