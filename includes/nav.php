<?php
// Shared role-aware navigation include
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!defined('APP_BASE')) define('APP_BASE', '');
require_once __DIR__ . '/../helpers/SecurityHelper.php';

$user = $_SESSION['user'] ?? ['username' => 'Guest', 'first_name' => '', 'role' => 'student'];
$role = $user['role'] ?? 'student';
$initial = strtoupper(substr($user['first_name'] ?? $user['username'] ?? 'U', 0, 1));
?>
<aside class="sidebar">
  <div class="profile">
    <div class="avatar"><?= htmlspecialchars($initial) ?></div>
    <div>
      <div class="welcome">Welcome</div>
      <div class="username"><?= htmlspecialchars($user['first_name'] ?? $user['username']) ?></div>
      <div class="muted" style="font-size:0.9rem">Role: <?= htmlspecialchars(ucfirst($role)) ?></div>
    </div>
  </div>

  <nav>
    <?php if ($role === 'admin'): ?>
      <a href="<?= APP_BASE ?>/admin/dashboard.php">Admin Dashboard</a>
      <a href="<?= APP_BASE ?>/admin/users.php">Users</a>
      <a href="<?= APP_BASE ?>/admin/analytics.php">Analytics</a>
      <a href="<?= APP_BASE ?>/admin/scholarships.php">Scholarships</a>
      <!-- Activity Logs link removed -->
      <a href="<?= APP_BASE ?>/auth/logout.php">Logout</a>
    <?php elseif ($role === 'staff'): ?>
      <a href="<?= APP_BASE ?>/staff/dashboard.php">Staff Dashboard</a>
      <a href="<?= APP_BASE ?>/staff/applications.php">Applications Queue</a>
      <a href="<?= APP_BASE ?>/staff/scholarships.php">Manage Scholarships</a>
      <a href="<?= APP_BASE ?>/member/notifications.php">Notifications</a>
      <a href="<?= APP_BASE ?>/auth/logout.php">Logout</a>
    <?php else: ?>
      <a href="<?= APP_BASE ?>/member/dashboard.php">Dashboard</a>
      <a href="<?= APP_BASE ?>/member/applications.php">Your Applications</a>
      <a href="<?= APP_BASE ?>/member/apply_scholarship.php">Apply for Scholarship</a>
      <a href="<?= APP_BASE ?>/member/scholarships.php">Browse Scholarships</a>
      <a href="<?= APP_BASE ?>/member/profile.php">Profile</a>
      <a href="<?= APP_BASE ?>/member/notifications.php">Notifications <?= (!empty($unreadCount) && $unreadCount>0) ? '<span style="background:#e53935;color:#fff;padding:2px 6px;border-radius:10px;font-size:11px">' . intval($unreadCount) . '</span>' : '' ?></a>
      <a href="<?= APP_BASE ?>/auth/logout.php">Logout</a>
    <?php endif; ?>
  </nav>
</aside>
