<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user']['role'] ?? 'student';
?>
<style>
  body {
    background: var(--gray-50);
  }
  
  .dashboard-layout {
    display: flex;
    min-height: 100vh;
  }
  
  .sidebar {
    width: 280px;
    background: var(--white);
    border-right: 1px solid var(--gray-200);
    padding: var(--space-xl);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
  }
  
  .sidebar-logo {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--red-primary);
    margin-bottom: var(--space-2xl);
    text-decoration: none;
  }
  
  .sidebar-menu {
    list-style: none;
  }
  
  .sidebar-item {
    margin-bottom: var(--space-sm);
  }
  
  .sidebar-link {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-md);
    border-radius: var(--radius-lg);
    color: var(--gray-700);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
  }
  
  .sidebar-link:hover,
  .sidebar-link.active {
    background: var(--red-ghost);
    color: var(--red-primary);
  }
  
  .sidebar-icon {
    font-size: 1.25rem;
  }
  
  .main-content {
    flex: 1;
    margin-left: 280px;
    padding: var(--space-xl);
  }
  
  .page-header {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: var(--space-xl);
    margin-bottom: var(--space-xl);
    box-shadow: var(--shadow-sm);
  }
  
  .content-card {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: var(--space-xl);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--gray-200);
  }
  
  @media (max-width: 768px) {
    .sidebar {
      display: none;
    }
    
    .main-content {
      margin-left: 0;
    }
  }
</style>

<div class="dashboard-layout">
  <aside class="sidebar">
    <a href="<?= $base_path ?? '../' ?>index.php" class="sidebar-logo">
      <span>🎓</span>
      <span>ScholarHub</span>
    </a>
    
    <ul class="sidebar-menu">
      <?php if ($role === 'student'): ?>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>member/dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>member/scholarships.php" class="sidebar-link <?= $current_page === 'scholarships.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🎓</span>
            <span>Scholarships</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>member/applications.php" class="sidebar-link <?= $current_page === 'applications.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📝</span>
            <span>My Applications</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>member/notifications.php" class="sidebar-link <?= $current_page === 'notifications.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🔔</span>
            <span>Notifications</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>member/interview_booking.php" class="sidebar-link <?= $current_page === 'interview_booking.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📅</span>
            <span>Interview Booking</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>member/profile.php" class="sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">👤</span>
            <span>Profile</span>
          </a>
        </li>
      <?php elseif ($role === 'staff'): ?>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/scholarships.php" class="sidebar-link <?= $current_page === 'scholarships.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🎓</span>
            <span>Scholarships</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/applications.php" class="sidebar-link <?= $current_page === 'applications.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📝</span>
            <span>Applications</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/reports.php" class="sidebar-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span>
            <span>Reports</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/analytics.php" class="sidebar-link <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📈</span>
            <span>Analytics</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/documents.php" class="sidebar-link <?= $current_page === 'documents.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📄</span>
            <span>Documents</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/audit_logs.php" class="sidebar-link <?= $current_page === 'audit_logs.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📋</span>
            <span>Audit Logs</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/cron.php" class="sidebar-link <?= $current_page === 'cron.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">⚙️</span>
            <span>Automation</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>staff/profile.php" class="sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">👤</span>
            <span>Profile</span>
          </a>
        </li>
      <?php elseif ($role === 'admin'): ?>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/users.php" class="sidebar-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">👥</span>
            <span>Users</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/scholarships.php" class="sidebar-link <?= $current_page === 'scholarships.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🎓</span>
            <span>Scholarships</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/applications.php" class="sidebar-link <?= $current_page === 'applications.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📝</span>
            <span>Applications</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/announcements.php" class="sidebar-link <?= $current_page === 'announcements.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📢</span>
            <span>Announcements</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/analytics.php" class="sidebar-link <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📈</span>
            <span>Analytics</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/audit_logs.php" class="sidebar-link <?= $current_page === 'audit_logs.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📋</span>
            <span>Audit Logs</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/email_queue.php" class="sidebar-link <?= $current_page === 'email_queue.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📧</span>
            <span>Email Queue</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/interview_slots.php" class="sidebar-link <?= $current_page === 'interview_slots.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📅</span>
            <span>Interview Slots</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/fraud_detection.php" class="sidebar-link <?= $current_page === 'fraud_detection.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🛡️</span>
            <span>Fraud Detection</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>admin/profile.php" class="sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">👤</span>
            <span>Profile</span>
          </a>
        </li>
      <?php endif; ?>
      
      <li class="sidebar-item" style="margin-top: var(--space-xl); padding-top: var(--space-xl); border-top: 1px solid var(--gray-200);">
        <a href="<?= $base_path ?? '../' ?>auth/logout.php" class="sidebar-link">
          <span class="sidebar-icon">🚪</span>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </aside>

  <main class="main-content">
