<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, ['admin', 'staff'])) {
    $_SESSION['flash'] = 'Access denied.';
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getPDO();

// Get all scholarships with requirements
$stmt = $pdo->query('SELECT s.*, COUNT(e.id) as requirement_count FROM scholarships s LEFT JOIN eligibility_requirements e ON s.id = e.scholarship_id GROUP BY s.id ORDER BY s.created_at DESC');
$scholarships = $stmt->fetchAll();

// Get scholarship for editing
$edit_id = $_GET['edit'] ?? 0;
$edit_scholarship = null;
$edit_requirements = [];
if ($edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id');
    $stmt->execute([':id' => $edit_id]);
    $edit_scholarship = $stmt->fetch();
    if ($edit_scholarship) {
        $stmt = $pdo->prepare('SELECT requirement FROM eligibility_requirements WHERE scholarship_id = :id');
        $stmt->execute([':id' => $edit_id]);
        $edit_requirements = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Scholarship Management | Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
  <style>
    .form-modal { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    .requirement-item { margin-bottom: 10px; }
    .requirement-item input { width: calc(100% - 100px); display: inline-block; }
    .btn-add-req { padding: 8px 15px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .btn-remove-req { padding: 5px 10px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px; }
  </style>
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar"><?= $role == 'admin' ? 'A' : 'S' ?></div>
        <div>
          <div class="welcome"><?= ucfirst($role) ?></div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Applications</a>
        <a href="scholarships.php">Scholarships</a>
        <?php if ($role == 'admin'): ?>
          <a href="users.php">Users</a>
        <?php endif; ?>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>Scholarship Management</h2>
          <p class="muted">Create, edit, and manage scholarships</p>
        </div>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $edit_scholarship ? 'Edit Scholarship' : 'Create New Scholarship' ?></h3>
        <div class="form-modal">
          <form method="POST" action="../controllers/AdminController.php">

            <input type="hidden" name="action" value="<?= $edit_scholarship ? 'update_scholarship' : 'create_scholarship' ?>">
            <?php if ($edit_scholarship): ?>
              <input type="hidden" name="id" value="<?= $edit_scholarship['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
              <label>Title *</label>
              <input type="text" name="title" value="<?= htmlspecialchars($edit_scholarship['title'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label>Description</label>
              <textarea name="description" rows="4"><?= htmlspecialchars($edit_scholarship['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label>Organization</label>
              <input type="text" name="organization" value="<?= htmlspecialchars($edit_scholarship['organization'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Category</label>
              <select name="category">
                <option value="">Select category</option>
                <option value="Academic" <?= ($edit_scholarship['category'] ?? '') == 'Academic' ? 'selected' : '' ?>>Academic</option>
                <option value="Sports" <?= ($edit_scholarship['category'] ?? '') == 'Sports' ? 'selected' : '' ?>>Sports</option>
                <option value="Financial Aid" <?= ($edit_scholarship['category'] ?? '') == 'Financial Aid' ? 'selected' : '' ?>>Financial Aid</option>
                <option value="Other" <?= ($edit_scholarship['category'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>

            <div class="form-group">
              <label>Required Documents</label>
              <div id="documents-container">
                <div class="requirement-item">
                  <input type="text" name="documents[]" placeholder="e.g., Transcript of Records">
                  <button type="button" class="btn-remove-req" onclick="this.parentElement.remove()">Remove</button>
                </div>
              </div>
              <button type="button" class="btn-add-req" onclick="addDocument()">Add Document</button>
            </div>

            <div class="form-group">
              <label>GPA Requirement</label>
              <input type="number" step="0.01" min="0" max="5" name="gpa_requirement" value="<?= htmlspecialchars($edit_scholarship['gpa_requirement'] ?? '') ?>" placeholder="e.g., 3.5">
            </div>

            <div class="form-group">
              <label>Income Bracket Requirement</label>
              <input type="number" step="0.01" min="0" name="income_requirement" value="<?= htmlspecialchars($edit_scholarship['income_requirement'] ?? '') ?>" placeholder="e.g., 250000">
            </div>

            <div class="form-group">
              <label>Maximum Number of Scholars</label>
              <input type="number" min="1" name="max_scholars" value="<?= htmlspecialchars($edit_scholarship['max_scholars'] ?? '') ?>" placeholder="e.g., 10">
            </div>

            <div class="form-group">
              <label>Application Deadline</label>
              <input type="date" name="deadline" value="<?= htmlspecialchars($edit_scholarship['deadline'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Status</label>
              <select name="status">
                <option value="open" <?= ($edit_scholarship['status'] ?? 'open') == 'open' ? 'selected' : '' ?>>Open</option>
                <option value="closed" <?= ($edit_scholarship['status'] ?? '') == 'closed' ? 'selected' : '' ?>>Closed</option>
                <option value="archived" <?= ($edit_scholarship['status'] ?? '') == 'archived' ? 'selected' : '' ?>>Archived</option>
              </select>
            </div>

            <div class="form-group">
              <label>Auto-Close</label>
              <select name="auto_close">
                <option value="0" <?= ($edit_scholarship['auto_close'] ?? 0) == 0 ? 'selected' : '' ?>>No</option>
                <option value="1" <?= ($edit_scholarship['auto_close'] ?? 0) == 1 ? 'selected' : '' ?>>Yes</option>
              </select>
              <small>If enabled, scholarship will close automatically when deadline or max scholars is reached.</small>
            </div>

            <div class="form-group">
              <label>Eligibility Requirements</label>
              <div id="requirements-container">
                <?php if ($edit_scholarship && count($edit_requirements) > 0): ?>
                  <?php foreach ($edit_requirements as $req): ?>
                    <div class="requirement-item">
                      <input type="text" name="requirements[]" value="<?= htmlspecialchars($req) ?>" placeholder="e.g., GPA >= 3.5">
                      <button type="button" class="btn-remove-req" onclick="this.parentElement.remove()">Remove</button>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="requirement-item">
                    <input type="text" name="requirements[]" placeholder="e.g., GPA >= 3.5">
                    <button type="button" class="btn-remove-req" onclick="this.parentElement.remove()">Remove</button>
                  </div>
                <?php endif; ?>
              </div>
              <button type="button" class="btn-add-req" onclick="addRequirement()">Add Requirement</button>
            </div>

            <button type="submit" class="submit-btn"><?= $edit_scholarship ? 'Update Scholarship' : 'Create Scholarship' ?></button>
            <?php if ($edit_scholarship): ?>
              <a href="scholarships.php" style="margin-left:10px">Cancel</a>
            <?php endif; ?>
          </form>
        </div>
      </section>

      <section class="panel">
        <h3>All Scholarships</h3>
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Organization</th>
              <th>Status</th>
              <th>Requirements</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($scholarships as $s): ?>
              <tr style="border-top:1px solid #eee">
                <td><?= htmlspecialchars($s['id']) ?></td>
                <td><?= htmlspecialchars($s['title']) ?></td>
                <td><?= htmlspecialchars($s['organization'] ?? 'N/A') ?></td>
                <td>
                  <span style="color:<?= $s['status'] == 'open' ? 'green' : 'red' ?>">
                    <?= ucfirst($s['status']) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($s['requirement_count']) ?> requirements</td>
                <td><small><?= htmlspecialchars($s['created_at']) ?></small></td>
                <td>
                  <a href="?edit=<?= $s['id'] ?>" style="margin-right:10px">Edit</a>
                  <form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Delete this scholarship?')">
                    <input type="hidden" name="action" value="delete_scholarship">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" style="background:none;border:none;color:red;cursor:pointer;text-decoration:underline">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>

  <script>
    function addRequirement() {
      const container = document.getElementById('requirements-container');
      const div = document.createElement('div');
      div.className = 'requirement-item';
      div.innerHTML = '<input type="text" name="requirements[]" placeholder="e.g., GPA >= 3.5">' +
        '<button type="button" class="btn-remove-req" onclick="this.parentElement.remove()">Remove</button>';
      container.appendChild(div);
    }
  </script>
</body>
</html>
