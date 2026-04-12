<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];

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
  <p class="text-muted">Track your scholarship disbursements</p>
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
        <tr><th>Scholarship</th><th>Amount</th><th>Date</th><th>Method</th><th>Reference</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['scholarship_title']) ?></strong></td>
            <td>₱<?= number_format((float)$d['amount'], 2) ?></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td><?= htmlspecialchars($d['payment_method']) ?></td>
            <td><small><?= htmlspecialchars($d['transaction_reference'] ?? '—') ?></small></td>
            <td><span class="status-badge status-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
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
