<?php
/**
 * STUDENT — MY PAYOUTS
 * Role: Student
 * Purpose: View disbursement/payout history for approved scholarships
 * URL: /students/payouts.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];

// Auto-fix disbursements table schema
foreach ([
    "ALTER TABLE `disbursements` ADD COLUMN `application_id` INT DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `scholarship_id` INT DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `transaction_reference` VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL",
    "ALTER TABLE `disbursements` MODIFY COLUMN `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending'",
] as $_sql) {
    try { $pdo->exec($_sql); } catch (Exception $_e) { /* skip */ }
}
unset($_sql, $_e);

$disbursements = getDisbursements($pdo, [], 'student', $userId);
$total = array_sum(array_column(
    array_filter($disbursements, fn($d) => $d['status'] === 'completed'),
    'amount'
));

$page_title = 'My Payouts - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-money-bill-wave"></i> My Payouts</h1>
</div>

<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-value">₱<?= number_format($total, 2) ?></div>
    <div class="stat-label">Total Received</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= count(array_filter($disbursements, fn($d) => $d['status'] === 'pending')) ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= count(array_filter($disbursements, fn($d) => $d['status'] === 'processing')) ?></div>
    <div class="stat-label">Processing</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= count($disbursements) ?></div>
    <div class="stat-label">Total Records</div>
  </div>
</div>

<div class="content-card">
  <h2><i class="fas fa-clipboard-list"></i> Payout History</h2>

  <?php if (!empty($disbursements)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead>
        <tr><th>Scholarship</th><th>Amount</th><th>Date</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['scholarship_title']) ?></strong></td>
            <td>₱<?= number_format((float)$d['amount'], 2) ?></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td>
              <?php
                $statusColors = [
                  'pending'    => ['bg'=>'#fef3c7','color'=>'#92400e'],
                  'processing' => ['bg'=>'#dbeafe','color'=>'#1e40af'],
                  'completed'  => ['bg'=>'#d1fae5','color'=>'#065f46'],
                  'failed'     => ['bg'=>'#fee2e2','color'=>'#991b1b'],
                ];
                $sc = $statusColors[$d['status']] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280'];
              ?>
              <span style="padding:6px 16px;border-radius:9999px;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-weight:600;font-size:0.875rem;display:inline-block;">
                <?= ucfirst($d['status']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon"><i class="fas fa-money-bill-wave"></i></div>
      <h3 class="empty-state-title">No Payouts Yet</h3>
      <p class="empty-state-description">Your disbursement records will appear here once processed.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
