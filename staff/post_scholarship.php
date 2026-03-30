<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/AuditHelper.php';
requireLogin();
requireAnyRole(['admin','staff'], 'Staff access required');

$pdo = getPDO();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: post_scholarship.php');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $eligibility_requirements = trim($_POST['eligibility_requirements'] ?? '');
    $renewal_requirements = trim($_POST['renewal_requirements'] ?? '');
    $status = $_POST['status'] ?? 'open';
    $deadline = trim($_POST['deadline'] ?? '');
    $amount = trim($_POST['amount'] ?? '');

    if (!$title || !$organization) {
        $errors[] = 'Title and organization are required.';
    }
    if (!$deadline) {
        $errors[] = 'Application deadline is required.';
    }
    if (!$amount || !is_numeric($amount)) {
        $errors[] = 'Scholarship amount is required and must be a number.';
    }

    if (!$errors) {
        // Prevent duplicate scholarship entries
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM scholarships WHERE title = :title AND organization = :organization AND deadline = :deadline AND amount = :amount');
        $stmt->execute(['title' => $title, 'organization' => $organization, 'deadline' => $deadline, 'amount' => $amount]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Scholarship with this title, organization, deadline, and amount already exists.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO scholarships (title, description, organization, eligibility_requirements, renewal_requirements, status, deadline, amount) VALUES (:title, :description, :organization, :eligibility_requirements, :renewal_requirements, :status, :deadline, :amount)');
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'organization' => $organization,
            'eligibility_requirements' => $eligibility_requirements,
            'renewal_requirements' => $renewal_requirements,
            'status' => $status,
            'deadline' => $deadline,
            'amount' => $amount
        ]);
        $scholarship_id = (int)$pdo->lastInsertId();
        logAudit($pdo, $_SESSION['user_id'], 'SCHOLARSHIP_CREATED', 'scholarship', $scholarship_id, null, $title);
        header('Location: ../staff/scholarships.php?posted=1');
        exit;
    }
}

$csrf_token = generateCSRFToken();
$page_title = 'Post Scholarship - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>➕ Post Scholarship</h1>
  <p class="text-muted">Create a new scholarship opportunity</p>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $e): ?>
      <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="content-card">
  <a href="scholarships.php" class="btn btn-secondary" style="margin-bottom:var(--space-xl)">← Back to Scholarships</a>

  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="form-group">
      <label class="form-label">Title *</label>
      <input type="text" name="title" class="form-input" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Organization *</label>
      <input type="text" name="organization" class="form-input" required value="<?= htmlspecialchars($_POST['organization'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-input" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Eligibility Requirements</label>
      <textarea name="eligibility_requirements" class="form-input" rows="4"><?= htmlspecialchars($_POST['eligibility_requirements'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Renewal Requirements</label>
      <textarea name="renewal_requirements" class="form-input" rows="4"><?= htmlspecialchars($_POST['renewal_requirements'] ?? '') ?></textarea>
    </div>

    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Scholarship Amount *</label>
        <input type="number" name="amount" class="form-input" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Application Deadline *</label>
        <input type="date" name="deadline" class="form-input" required value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="open" <?= ($_POST['status'] ?? 'open') === 'open' ? 'selected' : '' ?>>Open</option>
        <option value="closed" <?= ($_POST['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
      </select>
    </div>

    <div style="margin-top:var(--space-xl)">
      <button type="submit" class="btn btn-primary">➕ Post Scholarship</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
