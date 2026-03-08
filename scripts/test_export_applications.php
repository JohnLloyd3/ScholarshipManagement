<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/AnalyticsHelper.php';

$pdo = getPDO();
$data = getApplicationsReport($pdo);

// Write CSV to temporary file for verification
$fp = fopen(__DIR__ . '/applications_export_sample.csv', 'w');
if (!empty($data)) {
    fputcsv($fp, array_keys($data[0]));
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
}
fclose($fp);

echo "Wrote sample CSV to scripts/applications_export_sample.csv\n";
?>