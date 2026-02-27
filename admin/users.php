<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

// Authentication
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'success';
unset($_SESSION['message']);
unset($_SESSION['message_type']);
$form_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['message'] = 'Invalid request (CSRF token missing or incorrect).';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $username = trim(strtolower($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $secret_question = trim($_POST['secret_question'] ?? '');
        $secret_answer = trim($_POST['secret_answer'] ?? '');
        
        $errors = [];
        
        // Validate first name
        if (empty($first_name)) {
            $errors['first_name'] = 'First name is required';
        } elseif (strlen($first_name) < 2) {
            $errors['first_name'] = 'First name must be at least 2 characters';
        } elseif (strlen($first_name) > 50) {
            $errors['first_name'] = 'First name must not exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $first_name)) {
            $errors['first_name'] = 'First name contains invalid characters';
        }
        
        // Validate last name
        if (empty($last_name)) {
            $errors['last_name'] = 'Last name is required';
        } elseif (strlen($last_name) < 2) {
            $errors['last_name'] = 'Last name must be at least 2 characters';
        } elseif (strlen($last_name) > 50) {
            $errors['last_name'] = 'Last name must not exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $last_name)) {
            $errors['last_name'] = 'Last name contains invalid characters';
        }
        
        // Validate email
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($email) > 150) {
            $errors['email'] = 'Email must not exceed 150 characters';
        }
        
        // Validate username
        if (empty($username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($username) > 50) {
            $errors['username'] = 'Username must not exceed 50 characters';
        } elseif (!preg_match('/^[a-z0-9._-]+$/', $username)) {
            $errors['username'] = 'Username can only contain letters, numbers, dots, underscores, and hyphens';
        }
        
        // Validate password
        if (empty($password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number';
        }
        
        // Validate password confirmation
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        // Validate role
        $valid_roles = ['admin', 'staff', 'student'];
        if (!in_array($role, $valid_roles)) {
            $errors['role'] = 'Invalid role selected';
        }

        // Validate secret question/answer (required)
        if (empty($secret_question)) {
            $errors['secret_question'] = 'Secret question is required';
        }
        if (empty($secret_answer)) {
            $errors['secret_answer'] = 'Secret answer is required';
        }
        
        if (empty($errors)) {
            try {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
                        $stmt->execute([':email' => $email, ':username' => $username]);
                        if ($stmt->fetch()) {
                            $_SESSION['message'] = 'Error: Email or username already exists';
                            $_SESSION['message_type'] = 'error';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                            $secretHash = password_hash($secret_answer, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare("
                                INSERT INTO users (first_name, last_name, email, username, password, role, phone, address, secret_question, secret_answer_hash, active)
                                VALUES (:first_name, :last_name, :email, :username, :password, :role, :phone, :address, :secret_question, :secret_answer_hash, 1)
                            ");
                            $stmt->execute([
                                ':first_name' => $first_name,
                                ':last_name' => $last_name,
                                ':email' => $email,
                                ':username' => $username,
                                ':password' => $hashedPassword,
                                ':role' => $role,
                                ':phone' => $phone,
                                ':address' => $address,
                                ':secret_question' => $secret_question,
                                ':secret_answer_hash' => $secretHash
                            ]);
                    $_SESSION['message'] = 'User created successfully!';
                    $_SESSION['message_type'] = 'success';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Validation error: ' . implode('; ', $errors);
            $_SESSION['message_type'] = 'error';
            $_SESSION['form_errors'] = $errors;
        }
        header('Location: users.php');
        exit;
    } elseif ($action === 'edit') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        
        $errors = [];
        
        // Validate ID
        if (!$id || $id <= 0) {
            $errors['id'] = 'Invalid user ID';
        }
        
        // Validate first name
        if (empty($first_name)) {
            $errors['first_name'] = 'First name is required';
        } elseif (strlen($first_name) < 2) {
            $errors['first_name'] = 'First name must be at least 2 characters';
        } elseif (strlen($first_name) > 50) {
            $errors['first_name'] = 'First name must not exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $first_name)) {
            $errors['first_name'] = 'First name contains invalid characters';
        }
        
        // Validate last name
        if (empty($last_name)) {
            $errors['last_name'] = 'Last name is required';
        } elseif (strlen($last_name) < 2) {
            $errors['last_name'] = 'Last name must be at least 2 characters';
        } elseif (strlen($last_name) > 50) {
            $errors['last_name'] = 'Last name must not exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z\s\'-]+$/', $last_name)) {
            $errors['last_name'] = 'Last name contains invalid characters';
        }
        
        // Validate email
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($email) > 150) {
            $errors['email'] = 'Email must not exceed 150 characters';
        }
        
        if (empty($errors)) {
            try {
                // Check if email is already in use by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $stmt->execute([':email' => $email, ':id' => $id]);
                if ($stmt->fetch()) {
                    $_SESSION['message'] = 'Error: Email is already in use by another user';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $pdo->prepare("UPDATE users SET first_name = :fn, last_name = :ln, email = :email WHERE id = :id")
                        ->execute([':fn' => $first_name, ':ln' => $last_name, ':email' => $email, ':id' => $id]);
                    $_SESSION['message'] = 'User updated successfully!';
                    $_SESSION['message_type'] = 'success';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Validation error: ' . implode('; ', $errors);
            $_SESSION['message_type'] = 'error';
            $_SESSION['form_errors'] = $errors;
        }
        header('Location: users.php');
        exit;
    } elseif ($action === 'delete') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $confirmation = $_POST['confirmation'] ?? '';
        
        // Validate deletion
        if (!$id || $id <= 0) {
            $_SESSION['message'] = 'Error: Invalid user ID';
            $_SESSION['message_type'] = 'error';
        } elseif ($id == $_SESSION['user_id']) {
            $_SESSION['message'] = 'Error: You cannot delete your own account';
            $_SESSION['message_type'] = 'error';
        } elseif ($confirmation !== 'yes') {
            $_SESSION['message'] = 'Error: Deletion not confirmed';
            $_SESSION['message_type'] = 'error';
        } else {
            try {
                // Verify user exists before deletion
                $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $_SESSION['message'] = 'Error: User not found';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
                    $_SESSION['message'] = 'User "' . sanitizeString($user['first_name'] . ' ' . $user['last_name']) . '" deleted successfully!';
                    $_SESSION['message_type'] = 'success';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        }
        header('Location: users.php');
        exit;
    } elseif ($action === 'activate_deactivate') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $current_status = intval($_POST['current_status'] ?? 1);
        $new_status = $current_status == 1 ? 0 : 1;
        
        if (!$id || $id <= 0) {
            $_SESSION['message'] = 'Error: Invalid user ID';
            $_SESSION['message_type'] = 'error';
        } elseif ($id == $_SESSION['user_id']) {
            $_SESSION['message'] = 'Error: You cannot change your own status';
            $_SESSION['message_type'] = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $_SESSION['message'] = 'Error: User not found';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $pdo->prepare("UPDATE users SET active = :status WHERE id = :id")
                        ->execute([':id' => $id, ':status' => $new_status]);
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    $_SESSION['message'] = 'User "' . sanitizeString($user['first_name'] . ' ' . $user['last_name']) . '" has been ' . $status_text . '!';
                    $_SESSION['message_type'] = 'success';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        }
    } elseif ($action === 'update_role') {
        $id = sanitizeInt($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? 'student';
        $valid_roles = ['admin', 'staff', 'student'];
        
        if (!$id || $id <= 0) {
            $_SESSION['message'] = 'Error: Invalid user ID';
            $_SESSION['message_type'] = 'error';
        } elseif ($id == $_SESSION['user_id']) {
            $_SESSION['message'] = 'Error: You cannot change your own role';
            $_SESSION['message_type'] = 'error';
        } elseif (!in_array($role, $valid_roles)) {
            $_SESSION['message'] = 'Error: Invalid role selected';
            $_SESSION['message_type'] = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $_SESSION['message'] = 'Error: User not found';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $pdo->prepare("UPDATE users SET role = :role WHERE id = :id")
                        ->execute([':id' => $id, ':role' => $role]);
                    $_SESSION['message'] = 'User "' . sanitizeString($user['first_name'] . ' ' . $user['last_name']) . '" role changed to ' . ucfirst($role) . '!';
                    $_SESSION['message_type'] = 'success';
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
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
$params = [];
if ($filter && in_array($filter, ['admin', 'staff', 'student'])) {
    $query .= " WHERE role = :filter";
    $params[':filter'] = $filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Count by role
$roleCounts = [];
foreach (['admin', 'staff', 'student'] as $role) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = '$role'");
    $roleCounts[$role] = $stmt->fetchColumn() ?: 0;
}

// Helper function to get field error class
function getFieldErrorClass($fieldName) {
    global $form_errors;
    return isset($form_errors[$fieldName]) ? 'field-error' : '';
}

// Helper function to get field error message
function getFieldError($fieldName) {
    global $form_errors;
    return $form_errors[$fieldName] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="../member/dashboard.css">
    <style>
        * { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', sans-serif; }
        body { background-color: #f8f9fa; color: #1a1a1a; }
        h2 { font-size: 28px; font-weight: 600; color: #1a1a1a; letter-spacing: -0.5px; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .navbar { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 15px; display: flex; justify-content: space-between; }
        .navbar a { color: white; margin-right: 20px; text-decoration: none; }
        
        .panel { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .role-tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 0; }
        .role-tab { padding: 12px 24px; cursor: pointer; text-decoration: none; color: #7f8c8d; border: none; background: none; font-size: 14px; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.3s ease; }
        .role-tab.active { color: #c41e3a; border-bottom-color: #c41e3a; }
        .role-tab:hover { color: #c41e3a; }
        
        .stat-card { background: linear-gradient(135deg, #c41e3a 0%, #8b1a1a 100%); color: white; padding: 24px; border-radius: 12px; display: inline-block; margin-right: 15px; margin-bottom: 15px; text-align: center; min-width: 140px; box-shadow: 0 4px 12px rgba(196,30,58,0.2); }
        .stat-card .number { font-size: 32px; font-weight: 700; line-height: 1; }
        .stat-card .label { font-size: 12px; opacity: 0.95; margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 500; }
        
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th, .table td { padding: 14px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background-color: #f8f9fa; font-weight: 600; color: #1a1a1a; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        .table td { color: #34495e; }
        .table tbody tr:hover { background: #f8f9fa; }
        
        .btn { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; font-weight: 500; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary { background-color: #c41e3a; color: white; }
        .btn-primary:hover { background-color: #9d1729; }
        .btn-success { background-color: #2d5016; color: white; }
        .btn-danger { background-color: #dc2626; color: white; }
        
        .status-active { background-color: #dcfce7; color: #16a34a; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .status-inactive { background-color: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        
        .message { padding: 16px; margin-bottom: 20px; border-radius: 8px; display: none; font-weight: 500; }
        .message.show { display: block; }
        .message.success { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .message.error { background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .message strong { display: block; margin-bottom: 5px; }
        
        .role-badge { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .role-admin { background-color: #fee2e2; color: #dc2626; }
        .role-staff { background-color: #dbeafe; color: #1e40af; }
        .role-student { background-color: #fed7aa; color: #9a3412; }
        
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto; padding-top: 60px; box-sizing: border-box; }
        .modal-content { background-color: white; margin: 0 auto; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px rgba(0,0,0,0.15); max-height: calc(100vh - 120px); overflow-y: auto; }
        .modal-header { font-size: 24px; font-weight: 600; margin-bottom: 20px; color: #1a1a1a; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1a1a1a; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: all 0.2s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #c41e3a; box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1); }
        
        .field-error input, .field-error select { border-color: #dc2626; }
        .field-error-message { color: #dc2626; font-size: 12px; margin-top: 5px; font-weight: 500; }
        
        .password-requirements { font-size: 12px; color: #7f8c8d; margin-top: 8px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 2px solid #c41e3a; }
        .requirement-item { display: flex; align-items: center; margin: 6px 0; }
        .requirement-item.met { color: #16a34a; }
        .requirement-item.unmet { color: #dc2626; }
        .requirement-item::before { content: '○ '; margin-right: 6px; font-weight: 600; }
        .requirement-item.met::before { content: '✓ '; }
        
        .btn-new-user { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="dashboard-app">
        <aside class="sidebar">
            <div class="profile">
                <div class="avatar">A</div>
                <div>
                    <div class="welcome">Admin</div>
                    <div class="username"><?= htmlspecialchars($_SESSION['user']['username']) ?></div>
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
        <?php if ($message): ?>
            <div class="message show <?= $message_type ?>">
                <strong><?= $message_type === 'success' ? '✓ Success' : '⚠ Error' ?></strong>
                <?= sanitizeString($message) ?>
            </div>
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
                <!-- reviewer role removed -->
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <div class="number"><?= $roleCounts['student'] ?></div>
                <div class="label">Students</div>
            </div>
        </div>

        <div class="panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Users</h2>
                <button class="btn btn-primary btn-new-user" onclick="openCreateModal()">+ New User</button>
            </div>
            
            <div class="role-tabs">
                <a href="users.php" class="role-tab <?= empty($filter) ? 'active' : '' ?>">All</a>
                <a href="?role=admin" class="role-tab <?= $filter === 'admin' ? 'active' : '' ?>">Admins</a>
                <a href="?role=staff" class="role-tab <?= $filter === 'staff' ? 'active' : '' ?>">Staff</a>
                <!-- reviewer role removed -->
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
                                            <!-- reviewer option removed -->
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
                                    <button class="btn btn-primary" style="padding: 5px 10px;" onclick="openEditModal(<?= $user['id'] ?>, '<?= sanitizeString($user['first_name']) ?>', '<?= sanitizeString($user['last_name']) ?>', '<?= sanitizeString($user['email']) ?>')">Edit</button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="activate_deactivate">
                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $user['active'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <button type="submit" class="btn <?= $user['active'] ? 'btn-danger' : 'btn-success' ?>" style="padding: 5px 10px;">
                                                <?= $user['active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <button class="btn btn-danger" style="padding: 5px 10px;" onclick="openDeleteModal(<?= $user['id'] ?>, '<?= sanitizeString($user['first_name']) ?> <?= sanitizeString($user['last_name']) ?>')">Delete</button>
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
    </main>
  </div>

    <!-- Create User Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Create New User</div>
            <form method="POST" id="createForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group <?= getFieldErrorClass('first_name') ?>">
                    <label>First Name * <small>(2-50 characters, letters only)</small></label>
                    <input type="text" name="first_name" required pattern="[a-zA-Z\s\'-]{2,50}" maxlength="50" placeholder="e.g., John">
                    <?php if ($error = getFieldError('first_name')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('last_name') ?>">
                    <label>Last Name * <small>(2-50 characters, letters only)</small></label>
                    <input type="text" name="last_name" required pattern="[a-zA-Z\s\'-]{2,50}" maxlength="50" placeholder="e.g., Doe">
                    <?php if ($error = getFieldError('last_name')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('email') ?>">
                    <label>Email *</label>
                    <input type="email" name="email" required maxlength="150" placeholder="user@example.com">
                    <?php if ($error = getFieldError('email')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('username') ?>">
                    <label>Username * <small>(3-50 characters, alphanumeric, dots, underscores, hyphens)</small></label>
                    <input type="text" name="username" required pattern="[a-z0-9._-]{3,50}" maxlength="50" placeholder="john_doe">
                    <?php if ($error = getFieldError('username')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('phone') ?>">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+63 912 345 6789">
                    <?php if ($error = getFieldError('phone')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= getFieldErrorClass('address') ?>">
                    <label>Address</label>
                    <textarea name="address" placeholder="Your address"></textarea>
                    <?php if ($error = getFieldError('address')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('password') ?>">
                    <label>Password * <small>(minimum 8 characters)</small></label>
                    <input type="password" name="password" id="createPassword" required minlength="8" placeholder="••••••••" oninput="validatePassword()">
                    <?php if ($error = getFieldError('password')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                    <div class="password-requirements">
                        <div class="requirement-item" id="req-length">At least 8 characters</div>
                        <div class="requirement-item" id="req-upper">At least one uppercase letter (A-Z)</div>
                        <div class="requirement-item" id="req-lower">At least one lowercase letter (a-z)</div>
                        <div class="requirement-item" id="req-number">At least one number (0-9)</div>
                    </div>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('confirm_password') ?>">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" id="createConfirmPassword" required minlength="8" placeholder="••••••••" oninput="validatePasswordMatch()">
                    <?php if ($error = getFieldError('confirm_password')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                    <div id="passwordMatchMessage" style="color: #f56565; font-size: 12px; margin-top: 5px; display: none;">Passwords do not match</div>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('role') ?>">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="">Select a role</option>
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                        <!-- reviewer option removed -->
                        <option value="admin">Admin</option>
                    </select>
                    <?php if ($error = getFieldError('role')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('secret_question') ?>">
                    <label>Secret Question</label>
                    <select name="secret_question" required>
                        <option value="">Select a question</option>
                        <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                        <option value="What is the name of your first pet?">What is the name of your first pet?</option>
                        <option value="What city were you born in?">What city were you born in?</option>
                        <option value="What is your favorite teacher's name?">What is your favorite teacher's name?</option>
                        <option value="What is your favorite food?">What is your favorite food?</option>
                    </select>
                    <?php if ($error = getFieldError('secret_question')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group <?= getFieldErrorClass('secret_answer') ?>">
                    <label>Secret Answer</label>
                    <input type="text" name="secret_answer" required placeholder="Enter your answer">
                    <?php if ($error = getFieldError('secret_answer')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                    <small>Keep it memorable. Do not share it with anyone.</small>
                </div>
                
                <button type="submit" class="btn btn-success">Create User</button>
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()" style="background-color: #718096;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Edit User</div>
            <form method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editUserId">
                
                <div class="form-group <?= getFieldErrorClass('first_name') ?>">
                    <label>First Name * <small>(2-50 characters, letters only)</small></label>
                    <input type="text" name="first_name" id="editFirstName" required pattern="[a-zA-Z\s\'-]{2,50}" maxlength="50">
                    <?php if ($error = getFieldError('first_name')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('last_name') ?>">
                    <label>Last Name * <small>(2-50 characters, letters only)</small></label>
                    <input type="text" name="last_name" id="editLastName" required pattern="[a-zA-Z\s\'-]{2,50}" maxlength="50">
                    <?php if ($error = getFieldError('last_name')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group <?= getFieldErrorClass('email') ?>">
                    <label>Email *</label>
                    <input type="email" name="email" id="editEmail" required maxlength="150">
                    <?php if ($error = getFieldError('email')): ?>
                        <div class="field-error-message"><?= sanitizeString($error) ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-success">Update User</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="background-color: #718096;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="color: #f56565;">⚠ Delete User</div>
            <p style="margin-bottom: 15px;">Are you sure you want to delete user <strong id="deleteUserName"></strong>? This action cannot be undone.</p>
            <div style="margin-bottom: 15px; padding: 10px; background: #f8d7da; border-radius: 4px; border: 1px solid #f5c6cb;">
                <small>This will permanently remove the user and all associated data.</small>
            </div>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteUserId">
                <input type="hidden" name="confirmation" value="yes">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: flex; align-items: center; font-weight: normal;">
                        <input type="checkbox" id="deleteConfirmation" style="width: auto; margin-right: 8px;" required>
                        Yes, I want to delete this user
                    </label>
                </div>
                
                <button type="submit" class="btn btn-danger" id="deleteSubmitBtn" disabled>Delete User</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()" style="background-color: #718096;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Password strength validation (safe checks if elements are missing)
        function validatePassword() {
            const pwEl = document.getElementById('createPassword');
            if (!pwEl) return;
            const password = pwEl.value || '';
            const requirements = {
                'req-length': password.length >= 8,
                'req-upper': /[A-Z]/.test(password),
                'req-lower': /[a-z]/.test(password),
                'req-number': /[0-9]/.test(password)
            };

            for (const [reqId, met] of Object.entries(requirements)) {
                const element = document.getElementById(reqId);
                if (!element) continue;
                if (met) {
                    element.classList.add('met');
                    element.classList.remove('unmet');
                } else {
                    element.classList.add('unmet');
                    element.classList.remove('met');
                }
            }
            validatePasswordMatch();
        }

        // Password match validation
        function validatePasswordMatch() {
            const pwEl = document.getElementById('createPassword');
            const confEl = document.getElementById('createConfirmPassword');
            const matchMessage = document.getElementById('passwordMatchMessage');
            if (!matchMessage || !confEl) return;
            const password = (pwEl && pwEl.value) || '';
            const confirmPassword = confEl.value || '';

            if (confirmPassword && password !== confirmPassword) {
                matchMessage.style.display = 'block';
            } else {
                matchMessage.style.display = 'none';
            }
        }

        // Modal functions
        function openCreateModal() {
            const modal = document.getElementById('createModal');
            modal.style.display = 'block';
            // Run validation after modal shown (addresses autofill and prefilled values)
            setTimeout(function() { validatePassword(); validatePasswordMatch(); }, 30);
        }

        function closeCreateModal() {
            const modal = document.getElementById('createModal');
            modal.style.display = 'none';
            const form = document.getElementById('createForm');
            if (form) form.reset();
            // Reset password requirements visuals
            document.querySelectorAll('.requirement-item').forEach(el => {
                el.classList.remove('met');
                el.classList.add('unmet');
            });
            const matchMessage = document.getElementById('passwordMatchMessage');
            if (matchMessage) matchMessage.style.display = 'none';
        }

        function openEditModal(id, firstName, lastName, email) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFirstName').value = firstName;
            document.getElementById('editLastName').value = lastName;
            document.getElementById('editEmail').value = email;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.style.display = 'none';
            const form = document.getElementById('editForm');
            if (form) form.reset();
        }

        function openDeleteModal(id, userName) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteUserName').textContent = userName;
            const checkbox = document.getElementById('deleteConfirmation');
            if (checkbox) checkbox.checked = false;
            const submitBtn = document.getElementById('deleteSubmitBtn');
            if (submitBtn) submitBtn.disabled = true;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
            const form = document.getElementById('deleteForm');
            if (form) form.reset();
        }

        // Setup event listeners after DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            // Enable delete button only when confirmation is checked
            const deleteCheckbox = document.getElementById('deleteConfirmation');
            if (deleteCheckbox) {
                deleteCheckbox.addEventListener('change', function() {
                    const submitBtn = document.getElementById('deleteSubmitBtn');
                    if (submitBtn) submitBtn.disabled = !this.checked;
                });
            }

            // If password fields are present and prefilled (autofill or validation errors), run validation
            const pwEl = document.getElementById('createPassword');
            if (pwEl && pwEl.value) validatePassword();
            const confEl = document.getElementById('createConfirmPassword');
            if (confEl && confEl.value) validatePasswordMatch();
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            let createModal = document.getElementById('createModal');
            let editModal = document.getElementById('editModal');
            let deleteModal = document.getElementById('deleteModal');
            if (event.target == createModal) {
                closeCreateModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
