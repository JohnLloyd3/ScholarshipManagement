<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

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
<?php
$page_title = 'Scholarship Management - ScholarHub';
$base_path = '../';
$csrf_token = generateCSRFToken();
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
	<h1>🎓 Scholarship Management</h1>
</div>
				</div>
			</div>
			<nav>
				<a href="dashboard.php">Dashboard</a>
				<a href="scholarships.php">Manage Scholarships</a>
				<a href="applications.php">View Applications</a>
				<a href="dashboard.php">Back to Dashboard</a>
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
						<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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
							<label>Status</label>
							<select name="status">
								<option value="open" <?= ($edit_scholarship['status'] ?? 'open') == 'open' ? 'selected' : '' ?>>Open</option>
								<option value="closed" <?= ($edit_scholarship['status'] ?? '') == 'closed' ? 'selected' : '' ?>>Closed</option>
							</select>
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
									<form method="POST" action="../controllers/AdminController.php" style="display:inline">
										<input type="hidden" name="action" value="update_status">
										<input type="hidden" name="id" value="<?= $s['id'] ?>">
										<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
										<select name="status" onchange="this.form.submit()">
											<option value="open" <?= $s['status']=='open'?'selected':'' ?>>Open</option>
											<option value="closed" <?= $s['status']=='closed'?'selected':'' ?>>Closed</option>
										</select>
									</form>
								</td>
								<td><?= htmlspecialchars($s['requirement_count']) ?> requirements</td>
								<td><small><?= htmlspecialchars($s['created_at']) ?></small></td>
								<td>
									<a href="?edit=<?= $s['id'] ?>" style="margin-right:10px">Edit</a>
									<form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Delete this scholarship?')">
										<input type="hidden" name="action" value="delete_scholarship">
										<input type="hidden" name="id" value="<?= $s['id'] ?>">
										<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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

