<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user']['role'] ?? 'student';

// Determine which sidebar item should be active based on current page
$active_section = '';
if (in_array($current_page, ['scholarships.php', 'scholarship_view.php', 'apply_scholarship.php'])) {
    $active_section = 'scholarships';
} elseif (in_array($current_page, ['applications.php', 'application_view.php'])) {
    $active_section = 'applications';
} elseif (in_array($current_page, ['notifications.php'])) {
    $active_section = 'notifications';
} elseif (in_array($current_page, ['announcements.php'])) {
    $active_section = 'announcements';
} elseif (in_array($current_page, ['interview.php', 'interview_management.php', 'interview_group_view.php'])) {
    $active_section = 'interviews';
} elseif (in_array($current_page, ['payouts.php', 'disbursements.php'])) {
    $active_section = 'payouts';
} elseif (in_array($current_page, ['feedback.php'])) {
    $active_section = 'feedback';
} elseif (in_array($current_page, ['profile.php'])) {
    $active_section = 'profile';
} elseif (in_array($current_page, ['dashboard.php'])) {
    $active_section = 'dashboard';
} elseif (in_array($current_page, ['users.php'])) {
    $active_section = 'users';
} elseif (in_array($current_page, ['analytics.php'])) {
    $active_section = 'analytics';
} elseif (in_array($current_page, ['documents.php', 'document_view.php'])) {
    $active_section = 'documents';
} elseif (in_array($current_page, ['pending_applications.php'])) {
    $active_section = 'pending';
}
?>
<style>
  /* ── Sidebar ── */
  .sidebar {
    width: 260px; position: fixed;
    top: 0; left: 0; bottom: 0; height: 100vh;
    background: #fff; border-right: 1.5px solid #D1D5DB;
    box-shadow: 2px 0 12px rgba(229,57,53,0.06);
    display: flex; flex-direction: column;
    overflow-y: auto; z-index: 300;
    scrollbar-width: thin; scrollbar-color: #D1D5DB transparent;
  }
  .sidebar::-webkit-scrollbar { width: 3px; }
  .sidebar::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 99px; }

  /* Brand */
  .sidebar-brand {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0 1.25rem;
    height: 72px;
    border-bottom: 1.5px solid #D1D5DB;
    text-decoration: none;
    flex-shrink: 0;
  }
  .sidebar-brand-icon {
    width: 34px; height: 34px; border-radius: 9px;
    background: #E53935; display: flex; align-items: center;
    justify-content: center; font-size: 1.1rem; flex-shrink: 0;
    color: #fff; overflow: hidden;
  }
  .sidebar-brand-icon img {
    width: 28px; height: 28px; object-fit: contain;
  }
  .sidebar-brand-name { font-size: 1.1rem; font-weight: 800; color: #1a1a2e; }



  /* Nav */
  .sidebar-nav { flex: 1; padding: 0.75rem 0.75rem; }
  .sidebar-menu { list-style: none; }
  .sidebar-item { margin-bottom: 2px; }
  .sidebar-link {
    display: flex; align-items: center; gap: 0.65rem;
    padding: 0.6rem 0.875rem; border-radius: 9px;
    color: #616161; text-decoration: none;
    font-size: 0.8375rem; font-weight: 500;
    transition: all 0.15s ease;
  }
  .sidebar-link:hover { background: #FFF5F5; color: #E53935; }
  .sidebar-link.active { background: #FFEBEE; color: #E53935; font-weight: 700; }
  .sidebar-icon { font-size: 0.95rem; width: 20px; text-align: center; flex-shrink: 0; }

  /* Bottom */
  .sidebar-bottom {
    padding: 0.75rem; border-top: 1.5px solid #D1D5DB; margin-top: auto;
  }

  @media (max-width: 768px) {
    .sidebar { display: none; }
  }
</style>

<div class="dashboard-layout">
  <aside class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand" style="cursor:default;">
      <div class="sidebar-brand-icon">
        <img src="<?= $base_path ?? '../' ?>assets/image/logo.svg" alt="ScholarHub Logo" style="width: 28px; height: 28px;">
      </div>
      <span class="sidebar-brand-name">ScholarHub</span>
    </div>



    <!-- Navigation -->
    <nav class="sidebar-nav">
      <ul class="sidebar-menu">

        <?php if ($role === 'student'): ?>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>students/dashboard.php" class="sidebar-link <?= $active_section==='dashboard'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-chart-line"></i></span>Dashboard</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>students/scholarships.php" class="sidebar-link <?= $active_section==='scholarships'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-graduation-cap"></i></span>Scholarships</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>students/applications.php" class="sidebar-link <?= $active_section==='applications'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-file-alt"></i></span>My Applications</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>students/interview.php" class="sidebar-link <?= $active_section==='interviews'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-calendar-alt"></i></span>Interview</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>students/payouts.php" class="sidebar-link <?= $active_section==='payouts'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-money-bill-wave"></i></span>My Payouts</a></li>

        <?php elseif ($role === 'staff'): ?>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/dashboard.php" class="sidebar-link <?= $active_section==='dashboard'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-chart-line"></i></span>Dashboard</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/scholarships.php" class="sidebar-link <?= $active_section==='scholarships'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-graduation-cap"></i></span>Scholarships</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/applications.php" class="sidebar-link <?= $active_section==='applications'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-file-alt"></i></span>Applications</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/pending_applications.php" class="sidebar-link <?= $active_section==='pending'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-hourglass-half"></i></span>Pending</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/interview_management.php" class="sidebar-link <?= $active_section==='interviews'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-calendar-alt"></i></span>Interview Management</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/disbursements.php" class="sidebar-link <?= $active_section==='payouts'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-money-bill-wave"></i></span>Disbursements</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/announcements.php" class="sidebar-link <?= $active_section==='announcements'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-bullhorn"></i></span>Announcements</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/feedback.php" class="sidebar-link <?= $active_section==='feedback'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-star"></i></span>Feedback</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/analytics.php" class="sidebar-link <?= $active_section==='analytics'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span>Analytics</a></li>

        <?php elseif ($role === 'admin'): ?>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/dashboard.php" class="sidebar-link <?= $active_section==='dashboard'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-chart-line"></i></span>Dashboard</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/users.php" class="sidebar-link <?= $active_section==='users'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-users"></i></span>Users</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/scholarships.php" class="sidebar-link <?= $active_section==='scholarships'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-graduation-cap"></i></span>Scholarships</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/applications.php" class="sidebar-link <?= $active_section==='applications'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-file-alt"></i></span>Applications</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/interview_management.php" class="sidebar-link <?= $active_section==='interviews'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-calendar-alt"></i></span>Interview Management</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/disbursements.php" class="sidebar-link <?= $active_section==='payouts'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-money-bill-wave"></i></span>Disbursements</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/announcements.php" class="sidebar-link <?= $active_section==='announcements'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-bullhorn"></i></span>Announcements</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/feedback.php" class="sidebar-link <?= $active_section==='feedback'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-star"></i></span>Feedback</a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/analytics.php" class="sidebar-link <?= $active_section==='analytics'?'active':'' ?>"><span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span>Analytics</a></li>
        <?php endif; ?>

      </ul>
    </nav>

    <!-- Bottom -->
    <div class="sidebar-bottom">
      <a href="<?= $base_path ?? '../' ?>auth/logout.php" class="sidebar-link" style="color:#E53935;">
        <span class="sidebar-icon"><i class="fas fa-sign-out-alt"></i></span>Logout
      </a>
    </div>

  </aside>

  <main class="main-content">
