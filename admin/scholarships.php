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
    <link rel="stylesheet" href="../member/dashboard.css">
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif; }
        body { background: #f8f9fa; color: #1a1a1a; }
        h2, h3 { color: #1a1a1a; font-weight: 600; letter-spacing: -0.5px; }
        h2 { font-size: 28px; }
        h3 { font-size: 18px; }
        
        .scholarships-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1a1a1a; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-family: inherit; font-size: 14px; transition: all 0.2s ease;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: #c41e3a; box-shadow: 0 0 0 3px rgba(196,30,58,0.1);
        }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .panel { background: white; padding: 24px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 500; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(196,30,58,0.2); }
        .btn-primary { background-color: #c41e3a; color: white; }
        .btn-primary:hover { background-color: #9d1729; }
        .btn-success { background-color: #2d5016; color: white; }
        .btn-danger { background-color: #dc2626; color: white; }
        .btn-secondary { background-color: #4b5563; color: white; }
        
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th, .table td { padding: 14px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background-color: #f8f9fa; font-weight: 600; color: #1a1a1a; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        .table td { color: #34495e; }
        .table tbody tr:hover { background: #f8f9fa; }
        
        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-open { background-color: #dbeafe; color: #1e40af; }
        .status-closed { background-color: #fee2e2; color: #dc2626; }
        
        .message { padding: 16px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .message.error { background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        
        .edit-form { background: #f8f9fa; padding: 24px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #c41e3a; }
        
        .dashboard-app { display: flex; min-height: 100vh; }
        .main { flex: 1; padding: 20px; }
        @media (max-width: 900px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .panel .table { width: 100%; }
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="dashboard-app">
        <aside class="sidebar">
            <div class="profile">
                <div class="avatar">A</div>
                <div>
                    <div class="welcome">Admin</div>
                    <div class="username"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></div>
                </div>
            </div>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="applications.php">Applications</a>
                <a href="scholarships.php">Scholarships</a>
                <a href="users.php">Users</a>
                <a href="analytics.php">Analytics</a>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </aside>

        <main class="main">
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
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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

                    <div class="form-group">
                        <label>Eligibility Requirements</label>
                        <textarea name="eligibility_requirements"><?= sanitizeString($editing['eligibility_requirements'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Renewal Requirements</label>
                        <textarea name="renewal_requirements"><?= sanitizeString($editing['renewal_requirements'] ?? '') ?></textarea>
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

                    <button type="submit" class="btn btn-success">Create Scholarship</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('newForm').style.display = 'none'">Cancel</button>
                </form>
            </div>

            <div class="panel">
                <h3>All Scholarships</h3>
                <?php if (!empty($scholarships)): ?>
                    <div class="table-responsive">
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
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $sch['id'] ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php else: ?>
                    <p>No scholarships created yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
