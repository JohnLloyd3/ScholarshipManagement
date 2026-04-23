<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$action = $_GET['action'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = 'Invalid request (CSRF token missing or incorrect).';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $post_action = $_POST['action'] ?? '';
    
    if ($post_action === 'create') {
        $title = sanitizeString($_POST['title'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '');
        $organization = sanitizeString($_POST['organization'] ?? '');
        $eligibility_requirements = sanitizeString($_POST['eligibility_requirements'] ?? '');
        $renewal_requirements = sanitizeString($_POST['renewal_requirements'] ?? '');
        $amount = sanitizeFloat($_POST['amount'] ?? 0);
        $deadline = $_POST['deadline'] ?? '';
        
        if ($title && $description && $deadline) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO scholarships (title, description, organization, eligibility_requirements, renewal_requirements, amount, deadline, status, created_by)
                    VALUES (:title, :description, :organization, :eligibility_requirements, :renewal_requirements, :amount, :deadline, 'open', :created_by)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':organization' => $organization,
                    ':eligibility_requirements' => $eligibility_requirements,
                    ':renewal_requirements' => $renewal_requirements,
                    ':amount' => $amount,
                    ':deadline' => $deadline,
                    ':created_by' => $_SESSION['user_id']
                ]);
                $newId = (int)$pdo->lastInsertId();
                // Sync to relational eligibility_requirements table
                if ($eligibility_requirements && $newId) {
                    try {
                        $pdo->prepare("DELETE FROM eligibility_requirements WHERE scholarship_id = :sid")->execute([':sid' => $newId]);
                        foreach (array_filter(array_map('trim', explode("\n", $eligibility_requirements))) as $req) {
                            $pdo->prepare("INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)")->execute([':sid' => $newId, ':req' => $req]);
                        }
                    } catch (Exception $e) { /* table may not exist yet */ }
                }
                $_SESSION['message'] = 'Scholarship created successfully!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($post_action === 'update') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $title = sanitizeString($_POST['title'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '');
        $organization = sanitizeString($_POST['organization'] ?? '');
        $eligibility_requirements = sanitizeString($_POST['eligibility_requirements'] ?? '');
        $renewal_requirements = sanitizeString($_POST['renewal_requirements'] ?? '');
        $amount = sanitizeFloat($_POST['amount'] ?? 0);
        $deadline = $_POST['deadline'] ?? '';
        $status = $_POST['status'] ?? 'open';
        
        if ($id && $title) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE scholarships
                    SET title = :title, description = :description, organization = :organization,
                        eligibility_requirements = :eligibility_requirements, renewal_requirements = :renewal_requirements,
                        amount = :amount, deadline = :deadline, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':title' => $title,
                    ':description' => $description,
                    ':organization' => $organization,
                    ':eligibility_requirements' => $eligibility_requirements,
                    ':renewal_requirements' => $renewal_requirements,
                    ':amount' => $amount,
                    ':deadline' => $deadline,
                    ':status' => $status
                ]);
                // Sync to relational eligibility_requirements table
                try {
                    $pdo->prepare("DELETE FROM eligibility_requirements WHERE scholarship_id = :sid")->execute([':sid' => $id]);
                    foreach (array_filter(array_map('trim', explode("\n", $eligibility_requirements))) as $req) {
                        $pdo->prepare("INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)")->execute([':sid' => $id, ':req' => $req]);
                    }
                } catch (Exception $e) { /* table may not exist yet */ }
                $_SESSION['message'] = 'Scholarship updated successfully!';
                header('Location: scholarships.php');
                exit;
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($post_action === 'delete') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        if ($id) {
            try {
                $pdo->prepare("DELETE FROM scholarships WHERE id = :id")->execute([':id' => $id]);
                $_SESSION['message'] = 'Scholarship deleted successfully!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch scholarships
$scholarships = $pdo->query("
    SELECT s.*, COUNT(a.id) as app_count
    FROM scholarships s
    LEFT JOIN applications a ON a.scholarship_id = s.id
    GROUP BY s.id
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<?php
$page_title = 'Manage Scholarships - Admin';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-graduation-cap"></i> Manage Scholarships</h1>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
    <?= sanitizeString($message) ?>
  </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-xl);">
  <h2>All Scholarships</h2>
  <button class="btn btn-primary" onclick="document.getElementById('newScholarshipModal').style.display='flex'">+ New Scholarship</button>
</div>

  <!-- Create Scholarship Modal -->
  <div id="newScholarshipModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal-content" style="max-width:620px;">
      <div class="modal-header">
        <h3>Create New Scholarship</h3>
        <button class="modal-close" onclick="document.getElementById('newScholarshipModal').style.display='none'">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="organization" value="">
        <input type="hidden" name="eligibility_requirements" value="">
        <input type="hidden" name="renewal_requirements" value="">
        
        <div class="form-group">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-input" required>
        </div>
        
        <div class="form-group">
          <label class="form-label">Description *</label>
          <textarea name="description" class="form-input" rows="3" required></textarea>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount (₱)</label>
            <input type="number" name="amount" class="form-input" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Deadline *</label>
            <input type="date" name="deadline" class="form-input" required>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('newScholarshipModal').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Scholarship</button>
        </div>
      </form>
    </div>
  </div>

  <div class="content-card">
    <h3 style="margin-bottom: var(--space-xl);">Scholarship List</h3>
    <?php if (!empty($scholarships)): ?>
      <table class="modern-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Organization</th>
            <th>Amount</th>
            <th>Deadline</th>
            <th>Applications</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($scholarships as $sch): ?>
            <tr>
              <td><?= sanitizeString($sch['title']) ?></td>
              <td><?= sanitizeString($sch['organization'] ?? 'N/A') ?></td>
              <td>₱<?= number_format($sch['amount'] ?? 0, 2) ?></td>
              <td><?= $sch['deadline'] ?? 'N/A' ?></td>
              <td><?= $sch['app_count'] ?? 0 ?></td>
              <td><span class="status-badge status-<?= $sch['status'] ?>"><?= $sch['status'] ?></span></td>
              <td>
                <div style="display:flex;gap:0.35rem;flex-wrap:wrap;align-items:center">
                  <button onclick="openEditModal(<?= htmlspecialchars(json_encode($sch)) ?>)" class="btn btn-primary btn-sm">Edit</button>
                  <form style="display:contents" method="POST" onsubmit="return confirm('Delete this scholarship?');">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $sch['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm" style="background:#dc2626">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-graduation-cap"></i></div>
        <h3 class="empty-state-title">No Scholarships Yet</h3>
        <p class="empty-state-description">Create your first scholarship to get started.</p>
      </div>
    <?php endif; ?>
  </div>

<!-- Edit Scholarship Modal -->
<div id="editScholarshipModal" class="modal">
  <div class="modal-content" style="max-width:620px;">
    <div class="modal-header">
      <h3>Edit Scholarship</h3>
      <button class="modal-close" onclick="document.getElementById('editScholarshipModal').style.display='none'">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit_id">
      <input type="hidden" name="organization" id="edit_organization">
      <input type="hidden" name="eligibility_requirements" id="edit_eligibility">
      <input type="hidden" name="renewal_requirements" id="edit_renewal">
      
      <div class="form-group">
        <label class="form-label">Title *</label>
        <input type="text" name="title" id="edit_title" class="form-input" required>
      </div>
      
      <div class="form-group">
        <label class="form-label">Description *</label>
        <textarea name="description" id="edit_description" class="form-input" rows="3" required></textarea>
      </div>
      
      <div class="form-row">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Amount (₱) *</label>
          <input type="number" name="amount" id="edit_amount" class="form-input" step="0.01" min="0" required>
        </div>
        <div class="form-group" style="flex:1;">
          <label class="form-label">Deadline *</label>
          <input type="date" name="deadline" id="edit_deadline" class="form-input" required>
        </div>
      </div>
      
      <div class="form-group">
        <label class="form-label">Status *</label>
        <select name="status" id="edit_status" class="form-input" required>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editScholarshipModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Scholarship</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(scholarship) {
  document.getElementById('edit_id').value = scholarship.id;
  document.getElementById('edit_title').value = scholarship.title || '';
  document.getElementById('edit_organization').value = scholarship.organization || '';
  document.getElementById('edit_description').value = scholarship.description || '';
  document.getElementById('edit_eligibility').value = scholarship.eligibility_requirements || '';
  document.getElementById('edit_renewal').value = scholarship.renewal_requirements || '';
  document.getElementById('edit_amount').value = scholarship.amount || 0;
  document.getElementById('edit_deadline').value = scholarship.deadline || '';
  document.getElementById('edit_status').value = scholarship.status || 'open';
  document.getElementById('editScholarshipModal').style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
