<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$action = $_GET['action'] ?? '';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    
    if ($post_action === 'create') {
        $title = sanitizeString($_POST['title'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '');
        $organization = sanitizeString($_POST['organization'] ?? '');
        $amount = sanitizeFloat($_POST['amount'] ?? 0);
        $deadline = $_POST['deadline'] ?? '';
        
        if ($title && $description && $deadline) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO scholarships (title, description, organization, amount, deadline, status, created_by)
                    VALUES (:title, :description, :organization, :amount, :deadline, 'open', :created_by)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':organization' => $organization,
                    ':amount' => $amount,
                    ':deadline' => $deadline,
                    ':created_by' => $_SESSION['user_id']
                ]);
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
        $amount = sanitizeFloat($_POST['amount'] ?? 0);
        $deadline = $_POST['deadline'] ?? '';
        $status = $_POST['status'] ?? 'open';
        
        if ($id && $title) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE scholarships
                    SET title = :title, description = :description, organization = :organization,
                        amount = :amount, deadline = :deadline, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':id' => $id,
                    ':title' => $title,
                    ':description' => $description,
                    ':organization' => $organization,
                    ':amount' => $amount,
                    ':deadline' => $deadline,
                    ':status' => $status
                ]);
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scholarships - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .scholarships-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-success { background-color: #48bb78; color: white; }
        .btn-danger { background-color: #f56565; color: white; }
        .btn-secondary { background-color: #718096; color: white; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background-color: #f5f5f5; font-weight: bold; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-open { background-color: #87CEEB; }
        .status-closed { background-color: #FFB6C6; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .edit-form { background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav style="background-color: #333; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
        <h2>🎓 Scholarship Management</h2>
        <div>
            <a href="dashboard.php" style="color: white; margin-right: 20px; text-decoration: none;">Dashboard</a>
            <a href="../auth/logout.php" style="color: white; text-decoration: none;">Logout</a>
        </div>
    </nav>

    <div class="scholarships-container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'Error') !== false ? 'error' : 'success' ?>">
                <?= sanitizeString($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'edit' && $editing): ?>
            <div class="edit-form">
                <h2>Edit Scholarship</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $editing['id'] ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" value="<?= sanitizeString($editing['title']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Organization</label>
                            <input type="text" name="organization" value="<?= sanitizeString($editing['organization'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" required><?= sanitizeString($editing['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount (₱)</label>
                            <input type="number" name="amount" step="0.01" value="<?= $editing['amount'] ?? 0 ?>">
                        </div>
                        <div class="form-group">
                            <label>Deadline</label>
                            <input type="date" name="deadline" value="<?= $editing['deadline'] ?? '' ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="open" <?= $editing['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="closed" <?= $editing['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            <option value="cancelled" <?= $editing['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">Update Scholarship</button>
                    <a href="scholarships.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <div class="header">
                <h1>Scholarships</h1>
                <button class="btn btn-primary" onclick="document.getElementById('newForm').style.display = 'block'">+ New Scholarship</button>
            </div>

            <div id="newForm" class="panel" style="display: none;">
                <h3>Create New Scholarship</h3>
                <form method="POST">
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

                    <button type="submit" class="btn btn-success">Create Scholarship</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('newForm').style.display = 'none'">Cancel</button>
                </form>
            </div>

            <div class="panel">
                <h3>All Scholarships</h3>
                <?php if (!empty($scholarships)): ?>
                    <table class="table">
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
                                        <a href="?action=edit&id=<?= $sch['id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Edit</a>
                                        <form style="display: inline;" method="POST" onsubmit="return confirm('Delete this scholarship?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $sch['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No scholarships created yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
