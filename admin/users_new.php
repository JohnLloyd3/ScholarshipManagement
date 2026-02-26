<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'activate_deactivate') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $current_status = $_POST['current_status'] ?? '1';
        $new_status = $current_status == '1' ? 0 : 1;
        
        if ($id && $id != $_SESSION['user_id']) { // Can't deactivate yourself
            try {
                $pdo->prepare("UPDATE users SET active = :status WHERE id = :id")
                    ->execute([':id' => $id, ':status' => $new_status]);
                $_SESSION['message'] = 'User status updated!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_role') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? 'student';
        
        if ($id && $id != $_SESSION['user_id']) { // Can't change your own role
            try {
                $pdo->prepare("UPDATE users SET role = :role WHERE id = :id")
                    ->execute([':id' => $id, ':role' => $role]);
                $_SESSION['message'] = 'User role updated!';
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch users
$filter = $_GET['role'] ?? '';
$query = "
    SELECT id, username, first_name, last_name, email, role, active, created_at, email_verified
    FROM users
";

if ($filter && in_array($filter, ['admin', 'staff', 'reviewer', 'student'])) {
    $query .= " WHERE role = '" . $pdo->quote($filter) . "'";
}

$query .= " ORDER BY created_at DESC";

$users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Count by role
$roleCounts = [];
foreach (['admin', 'staff', 'reviewer', 'student'] as $role) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = '$role'");
    $roleCounts[$role] = $stmt->fetchColumn() ?: 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; display: flex; justify-content: space-between; }
        .navbar a { color: white; margin-right: 20px; text-decoration: none; }
        .panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .role-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .role-tab { padding: 10px 20px; cursor: pointer; text-decoration: none; color: #667eea; border


: none; background: none; font-size: 14px; }
        .role-tab.active { border-bottom: 3px solid #667eea; }
        .role-tab:hover { opacity: 0.8; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; display: inline-block; margin-right: 15px; margin-bottom: 15px; text-align: center; }
        .stat-card .number { font-size: 28px; font-weight: bold; }
        .stat-card .label { font-size: 12px; opacity: 0.9; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background-color: #f5f5f5; font-weight: bold; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #667eea; color: white; }
        .btn-success { background-color: #48bb78; color: white; }
        .btn-danger { background-color: #f56565; color: white; }
        .status-active { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .status-inactive { background-color: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 4px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .role-badge { padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .role-admin { background-color: #f56565; color: white; }
        .role-staff { background-color: #4299e1; color: white; }
        .role-reviewer { background-color: #48bb78; color: white; }
        .role-student { background-color: #ed8936; color: white; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2>👥 User Management</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= sanitizeString($message) ?></div>
        <?php endif; ?>

        <div class="panel">
            <h2>User Statistics</h2>
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="number"><?= $roleCounts['admin'] ?></div>
                <div class="label">Admins</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="number"><?= $roleCounts['staff'] ?></div>
                <div class="label">Staff</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="number"><?= $roleCounts['reviewer'] ?></div>
                <div class="label">Reviewers</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="number"><?= $roleCounts['student'] ?></div>
                <div class="label">Students</div>
            </div>
        </div>

        <div class="panel">
            <h2>Users</h2>
            
            <div class="role-tabs">
                <a href="users.php" class="role-tab <?= empty($filter) ? 'active' : '' ?>">All</a>
                <a href="?role=admin" class="role-tab <?= $filter === 'admin' ? 'active' : '' ?>">Admins</a>
                <a href="?role=staff" class="role-tab <?= $filter === 'staff' ? 'active' : '' ?>">Staff</a>
                <a href="?role=reviewer" class="role-tab <?= $filter === 'reviewer' ? 'active' : '' ?>">Reviewers</a>
                <a href="?role=student" class="role-tab <?= $filter === 'student' ? 'active' : '' ?>">Students</a>
            </div>

            <?php if (!empty($users)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Email Verified</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?= sanitizeString($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                </td>
                                <td><?= sanitizeString($user['email']) ?></td>
                                <td><?= sanitizeString($user['username']) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <select name="role" onchange="this.form.submit()" style="padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                            <option value="reviewer" <?= $user['role'] === 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
                                            <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="<?= $user['active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $user['active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $user['email_verified'] ? '✓ Yes' : '✗ No' ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="activate_deactivate">
                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $user['active'] ?>">
                                            <button type="submit" class="btn <?= $user['active'] ? 'btn-danger' : 'btn-success' ?>" style="padding: 5px 10px;">
                                                <?= $user['active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
