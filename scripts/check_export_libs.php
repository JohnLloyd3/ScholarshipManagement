<?php
require_once __DIR__ . '/../vendor/autoload.php';

$available = [];
$available['PhpSpreadsheet'] = class_exists('\PhpOffice\\PhpSpreadsheet\\Spreadsheet');
$available['Dompdf'] = class_exists('\Dompdf\\Dompdf');

echo "PhpSpreadsheet available: " . ($available['PhpSpreadsheet'] ? 'yes' : 'no') . "\n";
echo "Dompdf available: " . ($available['Dompdf'] ? 'yes' : 'no') . "\n";

?>