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

// Fetch scholarship for editing
$editing = null;
if ($action === 'edit') {
    $id = sanitizeInt($_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM scholarships WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $editing = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
<?php
$page_title = 'Manage Scholarships - Admin';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🎓 Manage Scholarships</h1>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
    <?= sanitizeString($message) ?>
  </div>
<?php endif; ?>

<?php if ($action === 'edit' && $editing): ?>
  <div class="content-card" style="border-left: 4px solid var(--red-primary);">
    <h3 style="margin-bottom: var(--space-xl);">Edit Scholarship</h3>
    <form method="POST" style="max-width: 100%;">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= $editing['id'] ?>">
      
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <div style="margin-bottom: 0;">
          <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Title *</label>
          <input type="text" name="title" value="<?= sanitizeString($editing['title']) ?>" required 
                 style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem;">
        </div>
        <div style="margin-bottom: 0;">
          <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Organization</label>
          <input type="text" name="organization" value="<?= sanitizeString($editing['organization'] ?? '') ?>"
                 style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem;">
        </div>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Description *</label>
        <textarea name="description" required rows="4"
                  style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem; resize: vertical;"><?= sanitizeString($editing['description'] ?? '') ?></textarea>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Eligibility Requirements</label>
        <textarea name="eligibility_requirements" rows="3"
                  style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem; resize: vertical;"><?= sanitizeString($editing['eligibility_requirements'] ?? '') ?></textarea>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Renewal Requirements</label>
        <textarea name="renewal_requirements" rows="3"
                  style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem; resize: vertical;"><?= sanitizeString($editing['renewal_requirements'] ?? '') ?></textarea>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <div style="margin-bottom: 0;">
          <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Amount (₱)</label>
          <input type="number" name="amount" step="0.01" value="<?= $editing['amount'] ?? 0 ?>"
                 style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem;">
        </div>
        <div style="margin-bottom: 0;">
          <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Deadline *</label>
          <input type="date" name="deadline" value="<?= $editing['deadline'] ?? '' ?>" required
                 style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem;">
        </div>
      </div>

      <div style="margin-bottom: 1.5rem;">
        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Status *</label>
        <select name="status" required
                style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.9375rem;">
          <option value="open" <?= $editing['status'] === 'open' ? 'selected' : '' ?>>Open</option>
          <option value="closed" <?= $editing['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
          <option value="cancelled" <?= $editing['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
      </div>

      <div style="display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary">Update Scholarship</button>
        <a href="scholarships.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
<?php else: ?>
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-xl);">
    <h2>All Scholarships</h2>
    <button class="btn btn-primary" onclick="document.getElementById('newForm').style.display = 'block'">+ New Scholarship</button>
  </div>

  <div id="newForm" class="content-card" style="display: none; margin-bottom: var(--space-xl);">
    <h3 style="margin-bottom: var(--space-xl);">Create New Scholarship</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <input type="hidden" name="action" value="create">
      
      <div class="form-row">
        <div class="form-group">
          <label>Title *</label>
          <input type="text" name="title" required>
        </div>
        <div class="form-group">
          <label>Organization</label>
          <input type="text" name="organization">
        </div>
      </div>

      <div class="form-group">
        <label>Description *</label>
        <textarea name="description" required></textarea>
      </div>

      <div class="form-group">
        <label>Eligibility Requirements</label>
        <textarea name="eligibility_requirements"></textarea>
      </div>

      <div class="form-group">
        <label>Renewal Requirements</label>
        <textarea name="renewal_requirements"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Amount (₱)</label>
          <input type="number" name="amount" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label>Deadline *</label>
          <input type="date" name="deadline" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Create Scholarship</button>
      <button type="button" class="btn btn-ghost" onclick="document.getElementById('newForm').style.display = 'none'">Cancel</button>
    </form>
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
                  <a href="?action=edit&id=<?= $sch['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
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
        <div class="empty-state-icon">🎓</div>
        <h3 class="empty-state-title">No Scholarships Yet</h3>
        <p class="empty-state-description">Create your first scholarship to get started.</p>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
