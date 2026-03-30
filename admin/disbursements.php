<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

requireLogin();
requireAnyRole(['admin'], 'Admin access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// Filters
$filters = [
    'status'    => $_GET['status']    ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to'   => $_GET['date_to']   ?? null,
    'student'   => $_GET['student']   ?? null,
];

$disbursements = getDisbursements($pdo, $filters, 'admin', $userId);
$awards        = getEligibleAwards($pdo);

// Summary stats
$totalAmount = array_sum(array_column($disbursements, 'amount'));
$byStatus    = array_count_values(array_column($disbursements, 'status'));

$page_title = 'Disbursements - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>💰 Disbursements</h1>
  <p class="text-muted">Manage scholarship award payouts</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-value">₱<?= number_format($totalAmount, 2) ?></div>
    <div class="stat-label">Total Disbursed</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= count($disbursements) ?></div>
    <div class="stat-label">Total Records</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $byStatus['pending'] ?? 0 ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $byStatus['completed'] ?? 0 ?></div>
    <div class="stat-label">Completed</div>
  </div>
</div>

<!-- Filters + Actions -->
<div class="content-card" style="margin-bottom:var(--space-lg);">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:var(--space-md);align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Status</label>
      <select name="status" class="form-input">
        <option value="">All</option>
        <?php foreach(['pending','processed','completed','failed'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Date From</label>
      <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:150px;">
      <label>Date To</label>
      <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
    </div>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;">
      <label>Student Name</label>
      <input type="text" name="student" class="form-input" placeholder="Search student..." value="<?= htmlspecialchars($filters['student'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="disbursements.php" class="btn btn-ghost">Clear</a>
  </form>
</div>

<!-- Table -->
<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-lg);flex-wrap:wrap;gap:var(--space-md);">
    <h2>📋 Disbursement Records</h2>
    <div style="display:flex;gap:var(--space-sm);flex-wrap:wrap;">
      <!-- Export forms carry current filters -->
      <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filters['status'] ?? '') ?>">
        <input type="hidden" name="filter_date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        <input type="hidden" name="filter_date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        <input type="hidden" name="filter_student" value="<?= htmlspecialchars($filters['student'] ?? '') ?>">
        <button type="submit" class="btn btn-ghost btn-sm">📥 CSV</button>
      </form>
      <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
        <input type="hidden" name="action" value="export_pdf">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filters['status'] ?? '') ?>">
        <input type="hidden" name="filter_date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        <input type="hidden" name="filter_date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        <input type="hidden" name="filter_student" value="<?= htmlspecialchars($filters['student'] ?? '') ?>">
        <button type="submit" class="btn btn-ghost btn-sm">📄 PDF</button>
      </form>
      <button onclick="document.getElementById('createModal').style.display='block'" class="btn btn-primary btn-sm">➕ New Disbursement</button>
    </div>
  </div>

  <?php if (!empty($disbursements)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Scholarship</th>
          <th>Amount</th>
          <th>Date</th>
          <th>Method</th>
          <th>Reference</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($d['email']) ?></small>
            </td>
            <td><?= htmlspecialchars($d['scholarship_title']) ?></td>
            <td><strong>₱<?= number_format((float)$d['amount'], 2) ?></strong></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td><?= htmlspecialchars($d['payment_method']) ?></td>
            <td><small><?= htmlspecialchars($d['transaction_reference'] ?? '—') ?></small></td>
            <td>
              <span class="status-badge status-<?= $d['status'] ?>">
                <?= ucfirst($d['status']) ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                <!-- Status advance -->
                <?php if ($d['status'] === 'pending'): ?>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="processed">
                    <button type="submit" class="btn btn-ghost btn-sm" title="Mark Processed">⚙️</button>
                  </form>
                <?php elseif ($d['status'] === 'processed'): ?>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-ghost btn-sm" title="Mark Completed" style="color:#16a34a;">✅</button>
                  </form>
                  <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                    <input type="hidden" name="status" value="failed">
                    <button type="submit" class="btn btn-ghost btn-sm" title="Mark Failed" style="color:#dc2626;">❌</button>
                  </form>
                <?php endif; ?>
                <!-- Edit -->
                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($d)) ?>)" class="btn btn-ghost btn-sm" title="Edit">✏️</button>
                <!-- Delete -->
                <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;" onsubmit="return confirm('Delete this disbursement?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626;" title="Delete">🗑️</button>
                </form>
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
      <p class="empty-state-description">Create the first disbursement record.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal">
  <div class="modal-content" style="max-width:560px;">
    <div class="modal-header">
      <h2>➕ New Disbursement</h2>
      <span class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" action="../controllers/DisbursementController.php">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="form-group">
        <label>Award (Student / Scholarship) *</label>
        <select name="award_id" class="form-input" required>
          <option value="">Select award...</option>
          <?php foreach($awards as $aw): ?>
            <option value="<?= (int)$aw['id'] ?>">
              <?= htmlspecialchars($aw['first_name'] . ' ' . $aw['last_name']) ?> — <?= htmlspecialchars($aw['scholarship_title']) ?> (₱<?= number_format((float)$aw['award_amount'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Amount (₱) *</label>
          <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Disbursement Date *</label>
          <input type="date" name="disbursement_date" class="form-input" required value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Payment Method *</label>
        <select name="payment_method" class="form-input" required>
          <option value="">Select method...</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="GCash">GCash</option>
          <option value="Maya">Maya</option>
          <option value="Check">Check</option>
          <option value="Cash">Cash</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Transaction Reference</label>
        <input type="text" name="transaction_reference" class="form-input" placeholder="Optional reference number">
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-textarea" rows="2" placeholder="Optional notes"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="modal-content" style="max-width:560px;">
    <div class="modal-header">
      <h2>✏️ Edit Disbursement</h2>
      <span class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
    </div>
    <form method="POST" action="../controllers/DisbursementController.php">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="disbursement_id" id="edit_id">
      <div class="form-row">
        <div class="form-group">
          <label>Amount (₱) *</label>
          <input type="number" name="amount" id="edit_amount" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label>Disbursement Date *</label>
          <input type="date" name="disbursement_date" id="edit_date" class="form-input" required>
        </div>
      </div>
      <div class="form-group">
        <label>Payment Method *</label>
        <select name="payment_method" id="edit_method" class="form-input" required>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="GCash">GCash</option>
          <option value="Maya">Maya</option>
          <option value="Check">Check</option>
          <option value="Cash">Cash</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Transaction Reference</label>
        <input type="text" name="transaction_reference" id="edit_ref" class="form-input">
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="edit_notes" class="form-textarea" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(d) {
  document.getElementById('edit_id').value     = d.id;
  document.getElementById('edit_amount').value = d.amount;
  document.getElementById('edit_date').value   = d.disbursement_date;
  document.getElementById('edit_method').value = d.payment_method;
  document.getElementById('edit_ref').value    = d.transaction_reference || '';
  document.getElementById('edit_notes').value  = d.notes || '';
  document.getElementById('editModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
