<?php
/**
 * Award Letter PDF Generator
 * Accessible by the scholar after their application is approved.
 * Pure PHP — no external library required.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireRole('student', 'Student access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$appId  = (int)($_GET['application_id'] ?? 0);

if (!$appId) {
    http_response_code(400);
    exit('Invalid request.');
}

// Load application — must belong to this student and be approved
$stmt = $pdo->prepare("
    SELECT a.id, a.created_at, a.reviewed_at, a.motivational_letter,
           u.first_name, u.last_name, u.email,
           s.title AS scholarship_title, s.organization, s.amount,
           s.description AS scholarship_desc
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.id = :aid AND a.user_id = :uid AND a.status = 'approved'
");
$stmt->execute([':aid' => $appId, ':uid' => $userId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    http_response_code(403);
    exit('Award letter not available. Application must be approved.');
}

// Parse applicant full name from form data if available
$fullName = trim($data['first_name'] . ' ' . $data['last_name']);
if ($data['motivational_letter']) {
    $form = json_decode($data['motivational_letter'], true);
    if (!empty($form['full_name'])) {
        $fullName = $form['full_name'];
    }
}

$scholarshipTitle = $data['scholarship_title'];
$organization     = $data['organization'] ?: 'ScholarHub';
$amount           = number_format((float)$data['amount'], 2);
$approvedDate     = $data['reviewed_at'] ? date('F d, Y', strtotime($data['reviewed_at'])) : date('F d, Y');
$refNo            = 'SH-' . date('Y') . '-' . str_pad($appId, 5, '0', STR_PAD_LEFT);
$generatedDate    = date('F d, Y');

// ── Pure PHP PDF ──────────────────────────────────────────────────────────────
$pageW  = 595.28; // A4 portrait
$pageH  = 841.89;
$margin = 60;

// Escape PDF string
$esc = fn(string $s): string => str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)','',''], $s);

// Word-wrap helper (returns array of lines)
function pdfWrap(string $text, int $maxChars): array {
    $words = explode(' ', $text);
    $lines = [];
    $line  = '';
    foreach ($words as $word) {
        if (strlen($line . ' ' . $word) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $line === '' ? $word : $line . ' ' . $word;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines ?: [''];
}

$objects  = [];
$objCount = 0;
$addObj   = function(string $content) use (&$objects, &$objCount): int {
    $objCount++;
    $objects[$objCount] = $content;
    return $objCount;
};

$addObj(''); // 1 catalog placeholder
$addObj(''); // 2 page tree placeholder
$fontR  = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>');
$fontB  = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>');
$fontI  = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>');
$fontH  = $addObj('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

$stream = '';
$y = $pageH - $margin;

// ── Header bar ────────────────────────────────────────────────────────────────
$stream .= "0.769 0.118 0.227 rg\n"; // red
$stream .= "{$margin} " . ($y - 50) . " " . ($pageW - $margin * 2) . " 50 re f\n";
$stream .= "1 1 1 rg\n";
$stream .= "BT /F4 22 Tf " . ($margin + 12) . " " . ($y - 34) . " Td (" . $esc('ScholarHub') . ") Tj ET\n";
$stream .= "BT /F3 10 Tf " . ($pageW - $margin - 130) . " " . ($y - 22) . " Td (" . $esc('Scholarship Management System') . ") Tj ET\n";
$stream .= "BT /F3 9 Tf " . ($pageW - $margin - 130) . " " . ($y - 36) . " Td (" . $esc($organization) . ") Tj ET\n";
$y -= 70;

// ── Title ─────────────────────────────────────────────────────────────────────
$stream .= "0 0 0 rg\n";
$stream .= "BT /F2 18 Tf " . ($pageW / 2 - 80) . " {$y} Td (" . $esc('AWARD LETTER') . ") Tj ET\n";
$y -= 8;
// Underline
$stream .= "0.769 0.118 0.227 RG 1 w\n";
$stream .= ($pageW / 2 - 80) . " {$y} m " . ($pageW / 2 + 80) . " {$y} l S\n";
$y -= 24;

// ── Reference & Date ─────────────────────────────────────────────────────────
$stream .= "0 0 0 rg\n";
$stream .= "BT /F1 10 Tf {$margin} {$y} Td (" . $esc("Reference No: {$refNo}") . ") Tj ET\n";
$stream .= "BT /F1 10 Tf " . ($pageW - $margin - 140) . " {$y} Td (" . $esc("Date: {$generatedDate}") . ") Tj ET\n";
$y -= 30;

// ── Salutation ────────────────────────────────────────────────────────────────
$stream .= "BT /F1 11 Tf {$margin} {$y} Td (" . $esc("Dear {$fullName},") . ") Tj ET\n";
$y -= 22;

// ── Body paragraph 1 ─────────────────────────────────────────────────────────
$body1 = "We are pleased to inform you that you have been selected as a recipient of the {$scholarshipTitle} scholarship awarded by {$organization}.";
foreach (pdfWrap($body1, 80) as $line) {
    $stream .= "BT /F1 11 Tf {$margin} {$y} Td (" . $esc($line) . ") Tj ET\n";
    $y -= 16;
}
$y -= 8;

// ── Award details box ─────────────────────────────────────────────────────────
$boxY = $y;
$stream .= "0.95 0.95 0.95 rg\n";
$stream .= "{$margin} " . ($boxY - 80) . " " . ($pageW - $margin * 2) . " 80 re f\n";
$stream .= "0.769 0.118 0.227 RG 0.5 w\n";
$stream .= "{$margin} " . ($boxY - 80) . " " . ($pageW - $margin * 2) . " 80 re S\n";
$stream .= "0 0 0 rg\n";

$col1 = $margin + 12;
$col2 = $margin + 220;
$rowH = 18;
$by   = $boxY - 18;

$details = [
    ['Scholarship:', $scholarshipTitle],
    ['Awarding Organization:', $organization],
    ['Award Amount:', '₱' . $amount],
    ['Date of Approval:', $approvedDate],
];
foreach ($details as [$label, $value]) {
    $stream .= "BT /F2 10 Tf {$col1} {$by} Td (" . $esc($label) . ") Tj ET\n";
    $stream .= "BT /F1 10 Tf {$col2} {$by} Td (" . $esc($value) . ") Tj ET\n";
    $by -= $rowH;
}
$y = $boxY - 90;

// ── Body paragraph 2 ─────────────────────────────────────────────────────────
$body2 = "This award is granted in recognition of your academic merit and financial need. Please log in to your ScholarHub account to track your disbursement status and complete any required surveys or feedback.";
foreach (pdfWrap($body2, 80) as $line) {
    $stream .= "BT /F1 11 Tf {$margin} {$y} Td (" . $esc($line) . ") Tj ET\n";
    $y -= 16;
}
$y -= 20;

// ── Congratulations ───────────────────────────────────────────────────────────
$stream .= "BT /F2 12 Tf {$margin} {$y} Td (" . $esc('Congratulations and best wishes for your academic journey!') . ") Tj ET\n";
$y -= 40;

// ── Signature block ───────────────────────────────────────────────────────────
$stream .= "BT /F1 11 Tf {$margin} {$y} Td (" . $esc('Sincerely,') . ") Tj ET\n";
$y -= 30;
$stream .= "0.769 0.118 0.227 RG 0.5 w\n";
$stream .= "{$margin} {$y} m " . ($margin + 140) . " {$y} l S\n";
$y -= 14;
$stream .= "0 0 0 rg\n";
$stream .= "BT /F2 11 Tf {$margin} {$y} Td (" . $esc('ScholarHub Administration') . ") Tj ET\n";
$y -= 14;
$stream .= "BT /F1 10 Tf {$margin} {$y} Td (" . $esc($organization) . ") Tj ET\n";

// ── Footer ────────────────────────────────────────────────────────────────────
$footerY = $margin + 20;
$stream .= "0.6 0.6 0.6 rg\n";
$stream .= "BT /F1 8 Tf {$margin} {$footerY} Td (" . $esc("This is an official award letter generated by ScholarHub. Reference: {$refNo}") . ") Tj ET\n";
$stream .= "BT /F1 8 Tf {$margin} " . ($footerY - 12) . " Td (" . $esc("Generated on {$generatedDate} | " . $data['email']) . ") Tj ET\n";
// Footer line
$stream .= "0.769 0.118 0.227 RG 0.5 w\n";
$stream .= "{$margin} " . ($footerY + 14) . " m " . ($pageW - $margin) . " " . ($footerY + 14) . " l S\n";

// ── Assemble PDF ──────────────────────────────────────────────────────────────
$len = strlen($stream);
$pageContentId = $addObj("<< /Length {$len} >>\nstream\n{$stream}\nendstream");
$pageId = $addObj(
    "<< /Type /Page /Parent 2 0 R " .
    "/MediaBox [0 0 {$pageW} {$pageH}] " .
    "/Contents {$pageContentId} 0 R " .
    "/Resources << /Font << /F1 {$fontR} 0 R /F2 {$fontB} 0 R /F3 {$fontI} 0 R /F4 {$fontH} 0 R >> >> >>"
);

$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
$objects[2] = "<< /Type /Pages /Kids [{$pageId} 0 R] /Count 1 >>";

$pdf  = "%PDF-1.4\n";
$xref = [];
for ($i = 1; $i <= $objCount; $i++) {
    $xref[$i] = strlen($pdf);
    $pdf .= "{$i} 0 obj\n{$objects[$i]}\nendobj\n";
}
$xrefOffset = strlen($pdf);
$pdf .= "xref\n0 " . ($objCount + 1) . "\n0000000000 65535 f \n";
for ($i = 1; $i <= $objCount; $i++) {
    $pdf .= str_pad($xref[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
}
$pdf .= "trailer\n<< /Size " . ($objCount + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

$filename = 'AwardLetter_' . preg_replace('/[^a-zA-Z0-9]/', '_', $fullName) . '_' . date('Y') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-cache');
echo $pdf;
exit;
