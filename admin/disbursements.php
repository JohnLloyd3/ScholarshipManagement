<?php
/**
 * ADMIN — DISBURSEMENTS
 * Role: Admin
 * Purpose: Track and manage scholarship payment disbursements per student
 * URL: /admin/disbursements.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/DisbursementHelper.php';

startSecureSession();
requireLogin();
requireRole('admin', 'Admin access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// Auto-fix disbursements table schema on every page load (safe — ignores already-existing columns)
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
    try { $pdo->exec($_sql); } catch (Exception $_e) { /* already exists — skip */ }
}
// Drop the award_id foreign key constraint (old schema blocks inserts without award_id)
try {
    $fkRow = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'disbursements'
        AND COLUMN_NAME = 'award_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1")->fetch();
    if ($fkRow) {
        $pdo->exec("ALTER TABLE `disbursements` DROP FOREIGN KEY `{$fkRow['CONSTRAINT_NAME']}`");
    }
} catch (Exception $_e) { /* ignore */ }
unset($_sql, $_e);

// Filters
$filters = [
    'status'      => !empty($_GET['status']) ? $_GET['status'] : null,
    'date_from'   => !empty($_GET['date_from']) ? $_GET['date_from'] : null,
    'date_to'     => !empty($_GET['date_to']) ? $_GET['date_to'] : null,
    'student'     => !empty($_GET['student']) ? $_GET['student'] : null,
    'scholarship' => !empty($_GET['scholarship']) ? $_GET['scholarship'] : null,
];

$disbursements = getDisbursements($pdo, $filters, 'admin', $userId);
$applications  = getEligibleApplications($pdo);

// Get all scholarships for filter dropdown
$scholarships = $pdo->query("SELECT DISTINCT id, title FROM scholarships ORDER BY title")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Summary stats
$totalAmount = array_sum(array_column($disbursements, 'amount'));
$byStatus    = array_count_values(array_column($disbursements, 'status'));

$page_title = 'Disbursements - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-money-bill-wave"></i> Disbursements</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid stats-grid-5" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div></div>
    <div class="stat-value">₱<?= number_format($totalAmount, 2) ?></div>
    <div class="stat-label">Total Disbursed</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-clipboard-list"></i></div></div>
    <div class="stat-value"><?= count($disbursements) ?></div>
    <div class="stat-label">Total Records</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div></div>
    <div class="stat-value"><?= $byStatus['pending'] ?? 0 ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon">🔄</div></div>
    <div class="stat-value"><?= $byStatus['processing'] ?? 0 ?></div>
    <div class="stat-label">Processing</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top"><div class="stat-icon">✅</div></div>
    <div class="stat-value"><?= $byStatus['completed'] ?? 0 ?></div>
    <div class="stat-label">Completed</div>
  </div>
</div>

<!-- Filters -->
<div class="filter-bar">
  <form method="GET" style="display:contents;">
    <div class="form-group">
      <label class="form-label">Status</label>
      <select name="status" class="form-input">
        <option value="">All</option>
        <?php foreach(['pending','processing','completed','failed'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['status'] === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Scholarship</label>
      <select name="scholarship" class="form-input">
        <option value="">All</option>
        <?php foreach($scholarships as $sch): ?>
          <option value="<?= (int)$sch['id'] ?>" <?= ($filters['scholarship'] == $sch['id']) ? 'selected' : '' ?>><?= htmlspecialchars($sch['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Date From</label>
      <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Date To</label>
      <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
    </div>
    <div class="form-group" style="flex:2;min-width:180px;">
      <label class="form-label">Student Name / ID</label>
      <input type="text" name="student" class="form-input" placeholder="Search by name, ID, or email..." value="<?= htmlspecialchars($filters['student'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="disbursements.php" class="btn btn-ghost">Clear</a>
  </form>
</div>

<!-- Table -->
<div class="content-card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h3 style="margin:0;"><i class="fas fa-clipboard-list"></i> Disbursement Records</h3>
    <button onclick="document.getElementById('createModal').style.display='flex'" class="btn btn-primary btn-sm">+ New Disbursement</button>
  </div>

  <?php if (!empty($disbursements)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Student</th>
          <th>Scholarship</th>
          <th>Amount</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($disbursements as $d): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></strong><br>
              <small class="text-muted">ID: <?= htmlspecialchars($d['student_id'] ?? 'N/A') ?></small><br>
              <small class="text-muted"><?= htmlspecialchars($d['email']) ?></small>
            </td>
            <td><?= htmlspecialchars($d['scholarship_title']) ?></td>
            <td><strong>₱<?= number_format((float)$d['amount'], 2) ?></strong></td>
            <td><?= !empty($d['disbursement_date']) ? date('M d, Y', strtotime($d['disbursement_date'])) : '—' ?></td>
            <td>
              <?php
                $statusColors = [
                  'pending'    => ['bg'=>'#fef3c7','color'=>'#92400e'],
                  'processing' => ['bg'=>'#dbeafe','color'=>'#1e40af'],
                  'completed'  => ['bg'=>'#d1fae5','color'=>'#065f46'],
                  'failed'     => ['bg'=>'#fee2e2','color'=>'#991b1b'],
                ];
                $nextStatus = ['pending'=>'processing','processing'=>'completed','completed'=>'completed','failed'=>'processing'];
                $sc = $statusColors[$d['status']] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280'];
                $next = $nextStatus[$d['status']] ?? null;
              ?>
              <?php if ($d['status'] !== 'completed'): ?>
                <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                  <input type="hidden" name="status" value="<?= htmlspecialchars($next) ?>">
                  <button type="submit" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:none;padding:4px 12px;border-radius:9999px;font-weight:600;font-size:0.78rem;cursor:pointer;" title="Click to advance status">
                    <?= ucfirst($d['status']) ?> ›
                  </button>
                </form>
              <?php else: ?>
                <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;padding:4px 12px;border-radius:9999px;font-weight:600;font-size:0.78rem;">
                  ✓ Completed
                </span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;">
                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($d)) ?>)" class="btn btn-ghost btn-sm" title="Edit"><i class="fas fa-edit"></i></button>
                <form method="POST" action="../controllers/DisbursementController.php" style="display:inline;" onsubmit="return confirm('Delete this disbursement?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="disbursement_id" value="<?= (int)$d['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:#dc2626;" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fas fa-money-bill-wave"></i></div>
      <h3 class="empty-state-title">No Disbursements</h3>
      <p class="empty-state-description">Create the first disbursement record.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Create Modal -->
<div id="createModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <span>New Disbursement</span>
      <button class="modal-close" onclick="document.getElementById('createModal').style.display='none'">&times;</button>
    </div>
    <form method="POST" action="../controllers/DisbursementController.php">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <div class="form-group">
        <label class="form-label">Application (Student / Scholarship) *</label>
        <select name="application_id" class="form-input" required>
          <option value="">Select application...</option>
          <?php foreach($applications as $app): ?>
            <option value="<?= (int)$app['id'] ?>">
              <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?> — <?= htmlspecialchars($app['scholarship_title']) ?> (₱<?= number_format((float)$app['application_amount'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount (₱) *</label>
          <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Disbursement Date *</label>
          <input type="date" name="disbursement_date" class="form-input" required value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Payment Method</label>
        <input type="text" class="form-input" value="Cash" disabled>
        <input type="hidden" name="payment_method" value="Cash">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-input" rows="2" placeholder="Optional notes"></textarea>
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
  <div class="modal-content">
    <div class="modal-header">
      <span>Edit Disbursement</span>
      <button class="modal-close" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
    </div>
    <form method="POST" action="../controllers/DisbursementController.php">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="disbursement_id" id="edit_id">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount (₱) *</label>
          <input type="number" name="amount" id="edit_amount" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Disbursement Date *</label>
          <input type="date" name="disbursement_date" id="edit_date" class="form-input" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" id="edit_notes" class="form-input" rows="2"></textarea>
      </div>
      <input type="hidden" name="payment_method" value="Cash">
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
  document.getElementById('edit_notes').value  = d.notes || '';
  document.getElementById('editModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
