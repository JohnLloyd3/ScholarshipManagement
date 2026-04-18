<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
// Audit helper removed
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

startSecureSession();

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/disbursements.php');
    exit;
}

$pdo    = getPDO();
$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];
$role   = $_SESSION['user']['role'] ?? 'student';

// ── CSRF validation ───────────────────────────────────────────────────────────
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request token.';
    header('Location: ' . (match($role) { 'admin' => '../admin/disbursements.php', 'staff' => '../staff/disbursements.php', default => '../member/payouts.php' }));
    exit;
}

// ── helpers ──────────────────────────────────────────────────────────────────
function flashAndRedirect(string $msg, string $url, string $key = 'flash'): never {
    $_SESSION[$key] = $msg;
    header("Location: $url");
    exit;
}

function backUrl(string $role): string {
    return match($role) {
        'admin' => '../admin/disbursements.php',
        'staff' => '../staff/disbursements.php',
        default => '../member/payouts.php',
    };
}

// ── create ────────────────────────────────────────────────────────────────────
if ($action === 'create') {
    if (!in_array($role, ['admin', 'staff'])) {
        flashAndRedirect('Access denied.', backUrl($role));
    }

    $awardId   = (int)($_POST['award_id'] ?? 0);
    $amount    = trim($_POST['amount'] ?? '');
    $date      = trim($_POST['disbursement_date'] ?? '');
    $method    = 'Cash';
    $reference = trim($_POST['transaction_reference'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if (!$awardId || !is_numeric($amount) || (float)$amount <= 0) {
        flashAndRedirect('Amount must be a positive number.', backUrl($role));
    }
    if (!$date) {
        flashAndRedirect('Disbursement date is required.', backUrl($role));
    }

    // Validate application exists and is approved
    $awardStmt = $pdo->prepare("SELECT a.id, a.user_id, a.scholarship_id, s.title AS scholarship_title FROM applications a JOIN scholarships s ON a.scholarship_id = s.id WHERE a.id = :id AND a.status = 'approved'");
    $awardStmt->execute([':id' => $awardId]);
    $award = $awardStmt->fetch(PDO::FETCH_ASSOC);

    if (!$award) {
        flashAndRedirect('Selected application is not eligible for disbursement.', backUrl($role));
    }

    try {
        // Auto-add missing columns so the insert never fails due to schema gaps
        $alterStatements = [
            "ALTER TABLE `disbursements` ADD COLUMN `application_id` INT DEFAULT NULL",
            "ALTER TABLE `disbursements` ADD COLUMN `scholarship_id` INT DEFAULT NULL",
            "ALTER TABLE `disbursements` ADD COLUMN `transaction_reference` VARCHAR(255) DEFAULT NULL",
            "ALTER TABLE `disbursements` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL",
            "ALTER TABLE `disbursements` ADD COLUMN `created_by` INT DEFAULT NULL",
            "ALTER TABLE `disbursements` MODIFY COLUMN `payment_method` VARCHAR(100) NOT NULL DEFAULT 'Cash'",
            "ALTER TABLE `disbursements` MODIFY COLUMN `award_id` INT DEFAULT NULL",
        ];
        foreach ($alterStatements as $sql) {
            try { $pdo->exec($sql); } catch (Exception $e) { /* column already exists — ignore */ }
        }
        // Drop award_id FK constraint (old schema)
        try {
            $fkRow = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disbursements'
                AND COLUMN_NAME = 'award_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1")->fetch();
            if ($fkRow) { $pdo->exec("ALTER TABLE `disbursements` DROP FOREIGN KEY `{$fkRow['CONSTRAINT_NAME']}`"); }
        } catch (Exception $e) { /* ignore */ }
        // Fix status ENUM
        try {
            $pdo->exec("ALTER TABLE `disbursements` MODIFY COLUMN `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending'");
        } catch (Exception $e) { /* ignore */ }

        $stmt = $pdo->prepare("
            INSERT INTO disbursements (application_id, user_id, scholarship_id, amount, disbursement_date, payment_method, status, notes, created_by, created_at)
            VALUES (:app_id, :user_id, :sch_id, :amount, :date, :method, 'pending', :notes, :created_by, NOW())
        ");
        $stmt->execute([
            ':app_id'     => $award['id'],
            ':user_id'    => $award['user_id'],
            ':sch_id'     => $award['scholarship_id'],
            ':amount'     => (float)$amount,
            ':date'       => $date,
            ':method'     => $method,
            ':notes'      => $notes ?: null,
            ':created_by' => $userId,
        ]);
        $disbId = (int)$pdo->lastInsertId();

        // Notify student
        $disbursement = getDisbursement($pdo, $disbId);
        if ($disbursement) {
            createDisbursementNotification($pdo, $award['user_id'], 'disbursement_created', $disbursement);
        }

        flashAndRedirect('Disbursement created successfully.', backUrl($role), 'success');
    } catch (Exception $e) {
        error_log('[DisbursementController] create error: ' . $e->getMessage());
        flashAndRedirect('An error occurred: ' . $e->getMessage(), backUrl($role));
    }
}

// ── update ────────────────────────────────────────────────────────────────────
if ($action === 'update') {
    if ($role !== 'admin') {
        flashAndRedirect('Access denied.', backUrl($role));
    }

    $disbId    = (int)($_POST['disbursement_id'] ?? 0);
    $amount    = trim($_POST['amount'] ?? '');
    $date      = trim($_POST['disbursement_date'] ?? '');
    $method    = 'Cash';
    $reference = trim($_POST['transaction_reference'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if (!$disbId || !is_numeric($amount) || (float)$amount <= 0) {
        flashAndRedirect('Amount must be a positive number.', backUrl($role));
    }
    if (!$date) flashAndRedirect('Disbursement date is required.', backUrl($role));

    $old = getDisbursement($pdo, $disbId);
    if (!$old) flashAndRedirect('Disbursement not found.', backUrl($role));

    try {
        $stmt = $pdo->prepare("
            UPDATE disbursements
            SET amount = :amount, disbursement_date = :date, payment_method = :method,
                notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([
            ':amount' => (float)$amount,
            ':date'   => $date,
            ':method' => $method,
            ':notes'  => $notes ?: null,
            ':id'     => $disbId,
        ]);

        
        flashAndRedirect('Disbursement updated.', backUrl($role), 'success');
    } catch (Exception $e) {
        error_log('[DisbursementController] update: ' . $e->getMessage());
        flashAndRedirect('An error occurred.', backUrl($role));
    }
}

// ── delete (soft) ─────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if ($role !== 'admin') {
        flashAndRedirect('Access denied.', backUrl($role));
    }

    $disbId = (int)($_POST['disbursement_id'] ?? 0);
    if (!$disbId) flashAndRedirect('Invalid request.', backUrl($role));

    try {
        $pdo->prepare("UPDATE disbursements SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $disbId]);
        flashAndRedirect('Disbursement deleted.', backUrl($role), 'success');
    } catch (Exception $e) {
        error_log('[DisbursementController] delete: ' . $e->getMessage());
        flashAndRedirect('An error occurred.', backUrl($role));
    }
}

// ── update_status ─────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    if (!in_array($role, ['admin', 'staff'])) {
        flashAndRedirect('Access denied.', backUrl($role));
    }

    $disbId    = (int)($_POST['disbursement_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    $disb = getDisbursement($pdo, $disbId);
    if (!$disb) flashAndRedirect('Disbursement not found.', backUrl($role));

    if (!isValidDisbursementTransition($disb['status'], $newStatus)) {
        flashAndRedirect('Invalid status transition.', backUrl($role));
    }

    try {
        $pdo->prepare("UPDATE disbursements SET status = :status WHERE id = :id")->execute([':status' => $newStatus, ':id' => $disbId]);

        if ($newStatus === 'completed') {
            createDisbursementNotification($pdo, $disb['user_id'], 'disbursement_completed', $disb);
        }

        $label = ucfirst($newStatus);
        flashAndRedirect("Status updated to {$label}.", backUrl($role), 'success');
    } catch (Exception $e) {
        error_log('[DisbursementController] update_status: ' . $e->getMessage());
        flashAndRedirect('An error occurred.', backUrl($role));
    }
}

// ── export_csv ────────────────────────────────────────────────────────────────
if ($action === 'export_csv') {
    if ($role !== 'admin') {
        flashAndRedirect('Access denied.', backUrl($role));
    }

    $filters = [
        'status'    => $_POST['filter_status'] ?? null,
        'date_from' => $_POST['filter_date_from'] ?? null,
        'date_to'   => $_POST['filter_date_to'] ?? null,
        'student'   => $_POST['filter_student'] ?? null,
    ];

    $rows = getDisbursementsForExport($pdo, $filters);
    

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="disbursements_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Student', 'Email', 'Scholarship', 'Amount', 'Date', 'Status', 'Notes', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['email'],
            $r['scholarship_title'],
            $r['amount'],
            $r['disbursement_date'],
            $r['status'],
            $r['notes'] ?? '',
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── export_pdf ────────────────────────────────────────────────────────────────
if ($action === 'export_pdf') {
    if ($role !== 'admin') {
        flashAndRedirect('Access denied.', backUrl($role));
    }

    $filters = [
        'status'    => $_POST['filter_status'] ?? null,
        'date_from' => $_POST['filter_date_from'] ?? null,
        'date_to'   => $_POST['filter_date_to'] ?? null,
        'student'   => $_POST['filter_student'] ?? null,
    ];

    $rows = getDisbursementsForExport($pdo, $filters);

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        flashAndRedirect('PDF export requires dompdf. Please install via Composer.', backUrl($role));
    }

    require_once $autoload;
    $html = '<html><head><style>
        body{font-family:Arial,sans-serif;font-size:11px;}
        h2{color:#2563eb;}
        table{width:100%;border-collapse:collapse;}
        th{background:#2563eb;color:white;padding:6px;text-align:left;}
        td{padding:5px;border-bottom:1px solid #eee;}
        .badge{padding:2px 6px;border-radius:3px;font-size:10px;}
        .pending{background:#fef3c7;color:#92400e;}
        .processed{background:#dbeafe;color:#1e40af;}
        .completed{background:#d1fae5;color:#065f46;}
        .failed{background:#fee2e2;color:#991b1b;}
    </style></head><body>';
    $html .= '<h2>Disbursements Report</h2>';
    $html .= '<p>Generated: ' . date('F d, Y g:i A') . '</p>';
    $html .= '<table><tr><th>ID</th><th>Student</th><th>Scholarship</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr>';
    foreach ($rows as $r) {
        $html .= '<tr>';
        $html .= '<td>' . (int)$r['id'] . '</td>';
        $html .= '<td>' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($r['scholarship_title']) . '</td>';
        $html .= '<td>₱' . number_format((float)$r['amount'], 2) . '</td>';
        $html .= '<td>' . htmlspecialchars($r['disbursement_date']) . '</td>';
        $html .= '<td>' . htmlspecialchars($r['payment_method']) . '</td>';
        $html .= '<td><span class="badge ' . $r['status'] . '">' . ucfirst($r['status']) . '</span></td>';
        $html .= '</tr>';
    }
    $html .= '</table></body></html>';

    try {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('disbursements_' . date('Y-m-d') . '.pdf', ['Attachment' => true]);
    } catch (Exception $e) {
        error_log('[DisbursementController] pdf: ' . $e->getMessage());
        flashAndRedirect('PDF generation failed.', backUrl($role));
    }
    exit;
}

flashAndRedirect('Invalid request.', backUrl($role));
