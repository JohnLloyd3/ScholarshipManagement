<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

requireLogin();
requireAnyRole(['admin', 'staff'], 'Staff access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// Auto-fix disbursements table schema
foreach ([
    "ALTER TABLE `disbursements` ADD COLUMN `application_id` INT DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `scholarship_id` INT DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `transaction_reference` VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL",
    "ALTER TABLE `disbursements` ADD COLUMN `created_by` INT DEFAULT NULL",
    "ALTER TABLE `disbursements` MODIFY COLUMN `payment_method` VARCHAR(100) NOT NULL DEFAULT 'Cash'",
    "ALTER TABLE `disbursements` MODIFY COLUMN `status` ENUM('pending','processing','completed','failed') DEFAULT 'pending'",
    "ALTER TABLE `disbursements` MODIFY COLUMN `award_id` INT DEFAULT NULL",
] as $_sql) {
    try { $pdo->exec($_sql); } catch (Exception $_e) { /* skip */ }
}
try {
    $fkRow = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disbursements'
        AND COLUMN_NAME = 'award_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1")->fetch();
    if ($fkRow) { $pdo->exec("ALTER TABLE `disbursements` DROP FOREIGN KEY `{$fkRow['CONSTRAINT_NAME']}`"); }
} catch (Exception $_e) {}
unset($_sql, $_e);

$filters = [
    'status'    => $_GET['status']    ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to'   => $_GET['date_to']   ?? null,
    'student'   => $_GET['student']   ?? null,
];

$disbursements = getDisbursements($pdo, $filters, 'admin', $userId);
$awards        = getEligibleAwards($pdo);

$page_title = 'Disbursements - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>💰 Disbursements</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card" style="margin-bottom:var(--space-lg);">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:var(--space-md);align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Status</label>
      <select name="status" class="form-input">
        <option value="">All</option>
        <?php foreach(['pending','processing','completed','failed'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Date From</label>
      <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Date To</label>
      <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
    </div>
    <div class="form-group" style="margin:0;flex:2;min-width:180px;">
      <label>Student</label>
      <input type="text" name="student" class="form-input" placeholder="Search..." value="<?= htmlspecialchars($filters['student'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="disbursements.php" class="btn btn-ghost">Clear</a>
  </form>
</div>

<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);">
    <h2>📋 Disbursement Records</h2>
    <button onclick="document.getElementById('createModal').style.display='block'" class="btn btn-primary btn-sm">➕ Record Payment</button>
  </div>

  <?php if (!empty($disbursements)): ?>
    <table class="modern-table">
      <thead>
        <tr><th>Student</th><th>Scholarship</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong></td>
            <td><?= htmlspecialchars($d['scholarship_title']) ?></td>
            <td>₱<?= number_format((float)$d['amount'], 2) ?></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td>
              <?php $step = ['pending'=>1,'processing'=>2,'completed'=>3,'failed'=>0][$d['status']] ?? 0; ?>
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
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;">
                <?php if ($d['status'] === 'pending'): ?>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="processing">
                    <button type="submit" class="btn btn-primary btn-sm">Mark Processing</button>
                  </form>
                <?php elseif ($d['status'] === 'processing'): ?>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-primary btn-sm" style="background:#16a34a;border-color:#16a34a;" onclick="return confirm('Mark as Completed? This will notify the student.')">✓ Complete</button>
                  </form>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="failed">
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626;">✗ Failed</button>
                  </form>
                <?php elseif ($d['status'] === 'completed'): ?>
                  <span style="color:#16a34a;font-weight:600;font-size:0.85rem;">✓ Paid</span>
                <?php elseif ($d['status'] === 'failed'): ?>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="processing">
                    <button type="submit" class="btn btn-ghost btn-sm">↺ Retry</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">💰</div>
      <h3 class="empty-state-title">No Disbursements</h3>
      <p class="empty-state-description">No records match your filters.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Create Modal (staff can record, not edit/delete) -->
<div id="createModal" class="modal">
  <div class="modal-content" style="max-width:520px;">
    <div class="modal-header">
      <h2>➕ Record Payment</h2>
      <span class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" action="../controllers/DisbursementController.php">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="form-group">
        <label>Award *</label>
        <select name="award_id" class="form-input" required>
          <option value="">Select award...</option>
          <?php foreach($awards as $aw): ?>
            <option value="<?= (int)$aw['id'] ?>">
              <?= htmlspecialchars($aw['first_name'] . ' ' . $aw['last_name']) ?> — <?= htmlspecialchars($aw['scholarship_title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Amount (₱) *</label>
          <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label>Date *</label>
          <input type="date" name="disbursement_date" class="form-input" required value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Payment Method</label>
        <input type="text" class="form-input" value="Cash" disabled>
        <input type="hidden" name="payment_method" value="Cash">
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-textarea" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Record</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
