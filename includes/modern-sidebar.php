<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user']['role'] ?? 'student';
?>
<style>
  /* ── Layout ── */
  html, body { height: 100%; margin: 0; padding: 0; }
  body { background: #fdf6f2; font-family: 'Inter', -apple-system, sans-serif; }
  .dashboard-layout { display: flex; min-height: 100vh; }

  /* ── Sidebar — Dark Neutral ── */
  .sidebar {
    width: 260px;
    background: #2d1f1a;
    padding: 0;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    height: 100%; min-height: 100vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.1) transparent;
    z-index: 100;
    display: flex;
    flex-direction: column;
  }
  .sidebar::-webkit-scrollbar { width: 3px; }
  .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

  /* Sidebar top section */
  .sidebar-top {
    padding: 20px 16px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }

  /* User card */
  .sidebar-user {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; margin-bottom: 16px;
    background: rgba(255,255,255,0.06);
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.08);
  }
  .sidebar-user-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    object-fit: cover; flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.2);
  }
  .sidebar-user-initials {
    width: 34px; height: 34px; border-radius: 50%;
    background: #2563eb;
    color: white; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
  }
  .sidebar-user-name {
    font-weight: 600; font-size: 0.8rem; color: #f9fafb;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .sidebar-user-role {
    font-size: 0.68rem; color: #9ca3af;
    text-transform: capitalize; margin-top: 1px;
  }

  /* Logo */
  .sidebar-logo {
    display: flex; align-items: center; gap: 10px;
    font-size: 1.1rem; font-weight: 800;
    color: #f9fafb !important;
    text-decoration: none; padding: 2px 4px;
    -webkit-text-fill-color: #f9fafb !important;
    background: none !important;
    -webkit-background-clip: unset !important;
  }
  .sidebar-logo .logo-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: #2563eb;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; flex-shrink: 0;
  }

  /* Nav section */
  .sidebar-nav { padding: 12px 8px; flex: 1; }

  /* Section label */
  .sidebar-section-label {
    font-size: 0.62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em;
    color: #6b7280; padding: 12px 12px 4px;
  }

  .sidebar-menu { list-style: none; margin: 0; padding: 0; }
  .sidebar-item { margin-bottom: 1px; }

  .sidebar-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px;
    color: #d1d5db; text-decoration: none;
    font-weight: 500; font-size: 0.845rem;
    transition: all 0.15s ease;
  }
  .sidebar-link:hover {
    background: rgba(255,255,255,0.07);
    color: #f9fafb;
  }
  .sidebar-link.active {
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 600;
  }
  .sidebar-link.active .sidebar-icon { opacity: 1; }
  .sidebar-icon { font-size: 0.95rem; flex-shrink: 0; width: 20px; text-align: center; opacity: 0.75; }
  .sidebar-link:hover .sidebar-icon { opacity: 1; }

  /* Sidebar bottom */
  .sidebar-bottom {
    padding: 12px 8px 20px;
    border-top: 1px solid rgba(255,255,255,0.06);
  }

  /* ── Main content ── */
  .main-content {
    flex: 1; margin-left: 260px;
    padding: 24px 28px;
    min-height: 100vh; background: #fdf6f2;
  }

  /* ── Page header — clean white ── */
  .page-header {
    background: #ffffff !important;
    border-radius: 12px !important;
    padding: 16px 24px !important;
    margin-bottom: 20px !important;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
    border: 1px solid #e5e7eb !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    position: relative !important;
    overflow: hidden !important;
  }
  .page-header::before {
    content: '' !important;
    position: absolute !important;
    left: 0; top: 0; bottom: 0; width: 4px !important;
    background: #2563eb !important;
    border-radius: 12px 0 0 12px !important;
    display: block !important;
  }
  .page-header h1 {
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    color: #111827 !important;
    margin: 0 !important;
    padding-left: 8px !important;
  }
  .page-header p, .page-header .text-muted {
    color: #6b7280 !important;
    font-size: 0.875rem !important;
    margin: 0 !important;
  }

  /* ── Content card ── */
  .content-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e5e7eb;
    transition: box-shadow 0.2s ease;
  }
  .content-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }

  @media (max-width: 768px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; padding: 16px; }
  }
</style>

<div class="dashboard-layout">
  <aside class="sidebar">

    <?php
    $sidebarUser   = $_SESSION['user'] ?? [];
    $sidebarUserId = $_SESSION['user_id'] ?? 0;
    $sidebarPic    = null;
    if ($sidebarUserId) {
        try {
            $picStmt = $pdo->prepare('SELECT first_name, last_name, profile_picture FROM users WHERE id = :id');
            $picStmt->execute([':id' => $sidebarUserId]);
            $picRow = $picStmt->fetch(PDO::FETCH_ASSOC);
            if ($picRow) {
                $sidebarUser = array_merge($sidebarUser, $picRow);
                $sidebarPic  = !empty($picRow['profile_picture']) && file_exists(__DIR__ . '/../' . $picRow['profile_picture'])
                    ? ($base_path ?? '../') . $picRow['profile_picture'] : null;
            }
        } catch (Exception $e) {}
    }
    $sidebarName = trim(($sidebarUser['first_name'] ?? '') . ' ' . ($sidebarUser['last_name'] ?? '')) ?: ($sidebarUser['username'] ?? 'User');
    ?>

    <div class="sidebar-top">
      <!-- User card -->
      <div class="sidebar-user">
        <?php if ($sidebarPic): ?>
          <img src="<?= htmlspecialchars($sidebarPic) ?>" alt="Profile" class="sidebar-user-avatar">
        <?php else: ?>
          <div class="sidebar-user-initials"><?= strtoupper(substr($sidebarName, 0, 1)) ?></div>
        <?php endif; ?>
        <div style="overflow:hidden;min-width:0;">
          <div class="sidebar-user-name"><?= htmlspecialchars($sidebarName) ?></div>
          <div class="sidebar-user-role"><?= htmlspecialchars($role) ?></div>
        </div>
      </div>
      <!-- Logo -->
      <a href="<?= $base_path ?? '../' ?>index.php" class="sidebar-logo">
        <div class="logo-icon">🎓</div>
        <span>ScholarHub</span>
      </a>
    </div>

    <div class="sidebar-nav">
      <ul class="sidebar-menu">

        <?php if ($role === 'student'): ?>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/dashboard.php" class="sidebar-link <?= $current_page==='dashboard.php'?'active':'' ?>"><span class="sidebar-icon">📊</span><span>Dashboard</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/scholarships.php" class="sidebar-link <?= $current_page==='scholarships.php'?'active':'' ?>"><span class="sidebar-icon">🎓</span><span>Scholarships</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/applications.php" class="sidebar-link <?= $current_page==='applications.php'?'active':'' ?>"><span class="sidebar-icon">📝</span><span>My Applications</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/notifications.php" class="sidebar-link <?= $current_page==='notifications.php'?'active':'' ?>"><span class="sidebar-icon">🔔</span><span>Notifications</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/announcements.php" class="sidebar-link <?= $current_page==='announcements.php'?'active':'' ?>"><span class="sidebar-icon">📢</span><span>Announcements</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/interview_booking.php" class="sidebar-link <?= $current_page==='interview_booking.php'?'active':'' ?>"><span class="sidebar-icon">📅</span><span>Interview Booking</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/payouts.php" class="sidebar-link <?= $current_page==='payouts.php'?'active':'' ?>"><span class="sidebar-icon">💰</span><span>My Payouts</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/feedback.php" class="sidebar-link <?= $current_page==='feedback.php'?'active':'' ?>"><span class="sidebar-icon">⭐</span><span>Feedback</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>member/profile.php" class="sidebar-link <?= $current_page==='profile.php'?'active':'' ?>"><span class="sidebar-icon">👤</span><span>Profile</span></a></li>

        <?php elseif ($role === 'staff'): ?>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/dashboard.php" class="sidebar-link <?= $current_page==='dashboard.php'?'active':'' ?>"><span class="sidebar-icon">📊</span><span>Dashboard</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/scholarships.php" class="sidebar-link <?= $current_page==='scholarships.php'?'active':'' ?>"><span class="sidebar-icon">🎓</span><span>Scholarships</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/applications.php" class="sidebar-link <?= $current_page==='applications.php'?'active':'' ?>"><span class="sidebar-icon">📝</span><span>Applications</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/pending_applications.php" class="sidebar-link <?= $current_page==='pending_applications.php'?'active':'' ?>"><span class="sidebar-icon">⏳</span><span>Pending</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/disbursements.php" class="sidebar-link <?= $current_page==='disbursements.php'?'active':'' ?>"><span class="sidebar-icon">💰</span><span>Disbursements</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/documents.php" class="sidebar-link <?= $current_page==='documents.php'?'active':'' ?>"><span class="sidebar-icon">📄</span><span>Documents</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/feedback.php" class="sidebar-link <?= $current_page==='feedback.php'?'active':'' ?>"><span class="sidebar-icon">⭐</span><span>Feedback</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/analytics.php" class="sidebar-link <?= $current_page==='analytics.php'?'active':'' ?>"><span class="sidebar-icon">📈</span><span>Analytics</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/reports.php" class="sidebar-link <?= $current_page==='reports.php'?'active':'' ?>"><span class="sidebar-icon">📊</span><span>Reports</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/cron.php" class="sidebar-link <?= $current_page==='cron.php'?'active':'' ?>"><span class="sidebar-icon">⚙️</span><span>Automation</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>staff/profile.php" class="sidebar-link <?= $current_page==='profile.php'?'active':'' ?>"><span class="sidebar-icon">👤</span><span>Profile</span></a></li>

        <?php elseif ($role === 'admin'): ?>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/dashboard.php" class="sidebar-link <?= $current_page==='dashboard.php'?'active':'' ?>"><span class="sidebar-icon">📊</span><span>Dashboard</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/users.php" class="sidebar-link <?= $current_page==='users.php'?'active':'' ?>"><span class="sidebar-icon">👥</span><span>Users</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/scholarships.php" class="sidebar-link <?= $current_page==='scholarships.php'?'active':'' ?>"><span class="sidebar-icon">🎓</span><span>Scholarships</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/applications.php" class="sidebar-link <?= $current_page==='applications.php'?'active':'' ?>"><span class="sidebar-icon">📝</span><span>Applications</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/announcements.php" class="sidebar-link <?= $current_page==='announcements.php'?'active':'' ?>"><span class="sidebar-icon">📢</span><span>Announcements</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/disbursements.php" class="sidebar-link <?= $current_page==='disbursements.php'?'active':'' ?>"><span class="sidebar-icon">💰</span><span>Disbursements</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/interview_slots.php" class="sidebar-link <?= $current_page==='interview_slots.php'?'active':'' ?>"><span class="sidebar-icon">📅</span><span>Interview Slots</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/interview_bookings.php" class="sidebar-link <?= $current_page==='interview_bookings.php'?'active':'' ?>"><span class="sidebar-icon">🗓️</span><span>Interview Bookings</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/feedback.php" class="sidebar-link <?= $current_page==='feedback.php'?'active':'' ?>"><span class="sidebar-icon">⭐</span><span>Feedback</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/fraud_detection.php" class="sidebar-link <?= $current_page==='fraud_detection.php'?'active':'' ?>"><span class="sidebar-icon">🛡️</span><span>Fraud Detection</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/analytics.php" class="sidebar-link <?= $current_page==='analytics.php'?'active':'' ?>"><span class="sidebar-icon">📈</span><span>Analytics</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/settings.php" class="sidebar-link <?= $current_page==='settings.php'?'active':'' ?>"><span class="sidebar-icon">⚙️</span><span>Settings</span></a></li>
          <li class="sidebar-item"><a href="<?= $base_path ?? '../' ?>admin/profile.php" class="sidebar-link <?= $current_page==='profile.php'?'active':'' ?>"><span class="sidebar-icon">👤</span><span>Profile</span></a></li>
        <?php endif; ?>

      </ul>
    </div>

    <div class="sidebar-bottom">
      <ul class="sidebar-menu">
        <li class="sidebar-item">
          <a href="<?= $base_path ?? '../' ?>auth/logout.php" class="sidebar-link">
            <span class="sidebar-icon">🚪</span><span>Logout</span>
          </a>
        </li>
      </ul>
    </div>

  </aside>

  <main class="main-content">
