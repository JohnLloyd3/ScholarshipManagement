<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

$pdo = getPDO();

// Get all users
$stmt = $pdo->query('SELECT id, username, first_name, last_name, email, role, active, email_verified, created_at FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>User Management | Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="../member/dashboard.css">
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
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>User Management</h2>
          <p class="muted">Manage user accounts, roles, and activation</p>
        </div>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <h3>All Users</h3>
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Email Verified</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr style="border-top:1px solid #eee">
                <td><?= htmlspecialchars($u['id']) ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                  <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <select name="role" onchange="this.form.submit()" <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                      <option value="student" <?= $u['role'] == 'student' ? 'selected' : '' ?>>Student</option>
                      <option value="staff" <?= $u['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
                      <option value="reviewer" <?= $u['role'] == 'reviewer' ? 'selected' : '' ?>>Reviewer</option>
                      <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                  </form>
                </td>
                <td>
                  <?php if ($u['active']): ?>
                    <span style="color:green">Active</span>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                      <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                        <input type="hidden" name="action" value="deactivate_user">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" style="margin-left:10px;padding:2px 8px;font-size:12px">Deactivate</button>
                      </form>
                    <?php endif; ?>
                  <?php else: ?>
                    <span style="color:red">Inactive</span>
                    <form style="display:inline" method="POST" action="../controllers/AdminController.php">
                      <input type="hidden" name="action" value="activate_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" style="margin-left:10px;padding:2px 8px;font-size:12px">Activate</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td><?= $u['email_verified'] ? '<span style="color:green">✓</span>' : '<span style="color:red">✗</span>' ?></td>
                <td>
                  <small><?= htmlspecialchars($u['created_at']) ?></small>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>
