<?php
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
  <h1>💰 My Payouts</h1>
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
  <h2>📋 Payout History</h2>

  <?php if (!empty($disbursements)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead>
        <tr><th>Scholarship</th><th>Amount</th><th>Date</th><th>Status</th><th>Award Letter</th></tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['scholarship_title']) ?></strong></td>
            <td>₱<?= number_format((float)$d['amount'], 2) ?></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td>
              <?php
                $step = ['pending'=>1,'processing'=>2,'completed'=>3,'failed'=>0][$d['status']] ?? 0;
              ?>
              <div style="display:flex;align-items:center;gap:3px;font-size:0.72rem;flex-wrap:wrap;">
                <span style="padding:2px 7px;border-radius:9999px;background:<?= $step>=1?'#fef3c7':'#f3f4f6' ?>;color:<?= $step>=1?'#92400e':'#9ca3af' ?>;font-weight:600;">Pending</span>
                <span style="color:#d1d5db;">›</span>
                <span style="padding:2px 7px;border-radius:9999px;background:<?= $step>=2?'#dbeafe':'#f3f4f6' ?>;color:<?= $step>=2?'#1e40af':'#9ca3af' ?>;font-weight:600;">Processing</span>
                <span style="color:#d1d5db;">›</span>
                <span style="padding:2px 7px;border-radius:9999px;background:<?= $step>=3?'#d1fae5':'#f3f4f6' ?>;color:<?= $step>=3?'#065f46':'#9ca3af' ?>;font-weight:600;">Completed</span>
                <?php if ($d['status']==='failed'): ?><span style="padding:2px 7px;border-radius:9999px;background:#fee2e2;color:#991b1b;font-weight:600;">Failed</span><?php endif; ?>
              </div>
            </td>
            <td>
              <?php if (!empty($d['application_id'])): ?>
                <a href="award_letter.php?application_id=<?= (int)$d['application_id'] ?>" class="btn btn-ghost btn-sm" target="_blank">📄 Download</a>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon">💰</div>
      <h3 class="empty-state-title">No Payouts Yet</h3>
      <p class="empty-state-description">Your disbursement records will appear here once processed.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
