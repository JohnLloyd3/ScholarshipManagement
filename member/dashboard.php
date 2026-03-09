<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please log in to access the dashboard.';
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
$pdo = null;
$dbError = false;
try {
    $pdo = getPDO();
} catch (Exception $e) {
    $dbError = true;
    error_log('[Dashboard] DB connection error: ' . $e->getMessage());
    // Don't show error message on every page load, only log it
    if (!isset($_SESSION['db_error_shown'])) {
        $_SESSION['flash'] = 'Database connection failed. Please ensure MySQL is running.';
        $_SESSION['db_error_shown'] = true;
    }
    $pdo = null;
}

$user = $_SESSION['user'] ?? [];
$user_id = $_SESSION['user_id'] ?? null;

// Helper: try multiple query patterns and return first successful count
function try_count($pdo, $queries)
{
    foreach ($queries as $q) {
        try {
            $stmt = $pdo->prepare($q['sql']);
            $stmt->execute($q['params']);
            $res = $stmt->fetchColumn();
            if ($res !== false) return (int)$res;
        } catch (Exception $e) {
            // ignore and try next
            continue;
        }
    }
    return 0;
}

$applicationsCount = 0;
$activeScholarships = 0;
$pendingReviews = 0;
$messagesCount = 0;

if ($pdo) {
  // Fetch published announcements for student dashboard
  try {
    $annStmt = $pdo->prepare("SELECT * FROM announcements WHERE published = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY published_at DESC LIMIT 5");
    $annStmt->execute();
    $announcements = $annStmt->fetchAll();
  } catch (Exception $e) {
    $announcements = [];
  }
    // Try to find a student profile linked to this user (uses canonical student_profiles table)
    $studentId = null;
    try {
      $stmt = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id = :uid LIMIT 1');
      $stmt->execute([':uid' => $user_id]);
      $r = $stmt->fetch();
      if ($r) {
        $studentId = $r['id'];
      } else {
        // try matching by email through users -> student_profiles
        $stmt = $pdo->prepare('SELECT sp.id FROM student_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.email = :email LIMIT 1');
        $stmt->execute([':email' => $user['email'] ?? '']);
        $r = $stmt->fetch();
        if ($r) $studentId = $r['id'];
      }
    } catch (Exception $e) {
      $studentId = null;
    }

    // Applications count: prefer counting by user_id (reliable schema), fallback to applicant email
    $applicationsCount = try_count($pdo, [
      ['sql' => 'SELECT COUNT(*) FROM applications WHERE user_id = :uid', 'params' => [':uid' => $user_id]],
      ['sql' => 'SELECT COUNT(*) FROM applications WHERE email = :email', 'params' => [':email' => $user['email'] ?? '']]
    ]);

    // Open scholarships available to apply (2.7 status tracking)
    $activeScholarships = try_count($pdo, [
        ['sql' => 'SELECT COUNT(*) FROM scholarships WHERE status = "open"', 'params' => []]
    ]);

    // Review workflow removed; no pending reviews to show
    $pendingReviews = 0;

    // Messages / notifications
    $messagesCount = try_count($pdo, [
        ['sql' => 'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND seen = 0', 'params' => [':uid' => $user_id]],
        ['sql' => 'SELECT COUNT(*) FROM notifications WHERE user_id = :uid', 'params' => [':uid' => $user_id]]
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - ScholarHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/modern-theme.css">
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
    
    .dashboard-header {
      background: var(--white);
      border-radius: var(--radius-xl);
      padding: var(--space-xl);
      margin-bottom: var(--space-xl);
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow-sm);
    }
    
    .user-info {
      display: flex;
      align-items: center;
      gap: var(--space-md);
    }
    
    .user-avatar {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-full);
      background: linear-gradient(135deg, var(--red-primary), var(--red-light));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.5rem;
      font-weight: 700;
    }
    
    .grid-2 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: var(--space-xl);
      margin-bottom: var(--space-xl);
    }
    
    .grid-4 {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-lg);
      margin-bottom: var(--space-xl);
    }
    
    .panel-section {
      background: var(--white);
      border-radius: var(--radius-xl);
      padding: var(--space-xl);
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--gray-200);
    }
    
    .panel-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: var(--space-lg);
    }
    
    @media (max-width: 768px) {
      .sidebar {
        display: none;
      }
      
      .main-content {
        margin-left: 0;
      }
      
      .dashboard-header {
        flex-direction: column;
        gap: var(--space-lg);
        text-align: center;
      }
    }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <a href="../index.php" class="sidebar-logo">
        <span>🎓</span>
        <span>ScholarHub</span>
      </a>
      
      <ul class="sidebar-menu">
        <li class="sidebar-item">
          <a href="dashboard.php" class="sidebar-link active">
            <span class="sidebar-icon">📊</span>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="scholarships.php" class="sidebar-link">
            <span class="sidebar-icon">🎓</span>
            <span>Scholarships</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="applications.php" class="sidebar-link">
            <span class="sidebar-icon">📝</span>
            <span>My Applications</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="notifications.php" class="sidebar-link">
            <span class="sidebar-icon">🔔</span>
            <span>Notifications</span>
          </a>
        </li>
        <li class="sidebar-item">
          <a href="profile.php" class="sidebar-link">
            <span class="sidebar-icon">👤</span>
            <span>Profile</span>
          </a>
        </li>
        <li class="sidebar-item" style="margin-top: var(--space-xl);">
          <a href="../auth/logout.php" class="sidebar-link">
            <span class="sidebar-icon">🚪</span>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Header -->
      <div class="dashboard-header">
        <div class="user-info">
          <div class="user-avatar">
            <?= strtoupper(substr($user['first_name'] ?? $user['username'] ?? 'U', 0, 1)) ?>
          </div>
          <div>
            <h2 style="margin-bottom: 0.25rem;">
              Welcome back, <?= htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'Student') ?>! 👋
            </h2>
            <p class="text-muted" style="margin: 0;">Here's what's happening with your scholarships</p>
          </div>
        </div>
        <a href="apply_scholarship.php" class="btn btn-primary">Apply for Scholarship</a>
      </div>

      <!-- Flash Messages -->
      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); unset($_SESSION['db_error_shown']); ?></div>
      <?php endif; ?>

      <!-- Stats Grid -->
      <div class="grid-4">
        <div class="stat-card">
          <div class="stat-icon">📝</div>
          <div class="stat-value"><?= htmlspecialchars($applicationsCount) ?></div>
          <div class="stat-label">Total Applications</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">🎯</div>
          <div class="stat-value"><?= htmlspecialchars($activeScholarships) ?></div>
          <div class="stat-label">Available Scholarships</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">⏳</div>
          <div class="stat-value">—</div>
          <div class="stat-label">Pending Reviews</div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon">💬</div>
          <div class="stat-value"><?= htmlspecialchars($messagesCount) ?></div>
          <div class="stat-label">Messages</div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="grid-2">
        <!-- Today's Applications -->
        <div class="panel-section">
          <h3 class="panel-title">Today's Applications</h3>
          <p class="text-muted">You have <?= htmlspecialchars($applicationsCount) ?> total application(s) recorded.</p>
          <a href="applications.php" class="btn btn-secondary btn-sm" style="margin-top: var(--space-md);">View All Applications</a>
        </div>
        
        <!-- Scholarship Status -->
        <div class="panel-section">
          <h3 class="panel-title">Scholarship Status</h3>
          <p class="text-muted">
            <?php if ($activeScholarships > 0): ?>
              There are <?= $activeScholarships ?> open scholarship(s) you can apply for.
            <?php else: ?>
              No open scholarships at the moment. Check back later.
            <?php endif; ?>
          </p>
          <a href="scholarships.php" class="btn btn-primary btn-sm" style="margin-top: var(--space-md);">Browse Scholarships</a>
        </div>
      </div>

      <!-- Announcements -->
      <?php if (!empty($announcements)): ?>
      <div class="panel-section">
        <h3 class="panel-title">📢 Announcements</h3>
        <?php foreach ($announcements as $ann): ?>
          <div style="padding: var(--space-lg); border-left: 4px solid var(--red-primary); background: var(--red-ghost); margin-bottom: var(--space-md); border-radius: var(--radius-lg);">
            <h4 style="font-weight: 600; margin-bottom: 0.5rem;"><?= htmlspecialchars($ann['title']) ?></h4>
            <p style="margin: 0.5rem 0; color: var(--gray-700);"><?= nl2br(htmlspecialchars($ann['message'])) ?></p>
            <small class="text-muted"><?= htmlspecialchars($ann['published_at']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Quick Actions -->
      <div class="panel-section">
        <h3 class="panel-title">Quick Actions</h3>
        <div style="display: flex; gap: var(--space-md); flex-wrap: wrap;">
          <a href="apply_scholarship.php" class="btn btn-primary">Apply for Scholarship</a>
          <a href="applications.php" class="btn btn-secondary">View Applications</a>
          <a href="profile.php" class="btn btn-ghost">Edit Profile</a>
        </div>
      </div>
    </main>
  </div>
</body>
</html>