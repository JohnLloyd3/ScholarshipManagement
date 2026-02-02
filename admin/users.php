<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_role('admin');

$pdo = getPDO();

// Filters
$q = trim($_GET['q'] ?? '');
$role = trim($_GET['role'] ?? '');
$active = trim($_GET['active'] ?? '');

$sql = 'SELECT id, username, first_name, last_name, email, role, active, created_at FROM users WHERE 1=1';
$params = [];

if ($q !== '') {
  $sql .= ' AND (username LIKE :q OR email LIKE :q OR first_name LIKE :q OR last_name LIKE :q)';
  $params[':q'] = '%' . $q . '%';
}
if ($role !== '' && in_array($role, ['admin','reviewer','student','staff'], true)) {
  $sql .= ' AND role = :role';
  $params[':role'] = $role;
}
if ($active !== '' && in_array($active, ['0','1'], true)) {
  $sql .= ' AND active = :active';
  $params[':active'] = (int)$active;
}

$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
        <form method="GET" style="margin: 10px 0; display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
          <div class="form-group" style="margin:0; min-width:240px;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="username, email, name">
          </div>
          <div class="form-group" style="margin:0;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Role</label>
            <select name="role">
              <option value="">All</option>
              <option value="student" <?= $role==='student'?'selected':'' ?>>Student</option>
              <option value="staff" <?= $role==='staff'?'selected':'' ?>>Staff</option>
              <option value="reviewer" <?= $role==='reviewer'?'selected':'' ?>>Reviewer</option>
              <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <label style="display:block;font-weight:bold;margin-bottom:6px;">Status</label>
            <select name="active">
              <option value="">All</option>
              <option value="1" <?= $active==='1'?'selected':'' ?>>Active</option>
              <option value="0" <?= $active==='0'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="form-group" style="margin:0;">
            <button type="submit" class="submit-btn" style="padding:10px 14px;">Filter</button>
            <a href="users.php" style="margin-left:10px;">Reset</a>
          </div>
        </form>
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
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
                <td>
                  <form style="display:inline" method="POST" action="../controllers/AdminController.php" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" style="background:none;border:none;color:red;cursor:pointer;text-decoration:underline" <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>Delete</button>
                  </form>
                  <span style="margin-left:10px"><small><?= htmlspecialchars($u['created_at']) ?></small></span>
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
