<?php
startSecureSession();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

requireLogin();
requireAnyRole(['admin', 'staff'], 'Staff access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

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
  <p class="text-muted">Record and view scholarship payouts</p>
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
        <tr><th>Student</th><th>Scholarship</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong></td>
            <td><?= htmlspecialchars($d['scholarship_title']) ?></td>
            <td>₱<?= number_format((float)$d['amount'], 2) ?></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td><?= htmlspecialchars($d['payment_method']) ?></td>
            <td><span class="status-badge status-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
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
        <label>Payment Method *</label>
        <select name="payment_method" class="form-input" required>
          <option value="">Select...</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="GCash">GCash</option>
          <option value="Maya">Maya</option>
          <option value="Check">Check</option>
          <option value="Cash">Cash</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Reference</label>
        <input type="text" name="transaction_reference" class="form-input" placeholder="Optional">
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
