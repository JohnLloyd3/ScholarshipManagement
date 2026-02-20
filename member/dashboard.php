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
    // Try to find a student id linked to this user
    $studentId = null;
    try {
        $stmt = $pdo->prepare('SELECT id FROM students WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $user_id]);
        $r = $stmt->fetch();
        if ($r) $studentId = $r['id'];
        else {
            // try matching by email
            $stmt = $pdo->prepare('SELECT id FROM students WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $user['email'] ?? '']);
            $r = $stmt->fetch();
            if ($r) $studentId = $r['id'];
        }
    } catch (Exception $e) {
        $studentId = null;
    }

    // Applications count
    if ($studentId) {
        $applicationsCount = try_count($pdo, [
            ['sql' => 'SELECT COUNT(*) FROM applications WHERE student_id = :id', 'params' => [':id' => $studentId]]
        ]);
    } else {
        // try counting applications by user_id or by applicant_email
        $applicationsCount = try_count($pdo, [
            ['sql' => 'SELECT COUNT(*) FROM applications WHERE user_id = :uid', 'params' => [':uid' => $user_id]],
            ['sql' => 'SELECT COUNT(*) FROM applications WHERE email = :email', 'params' => [':email' => $user['email'] ?? '']]
        ]);
    }

    // Open scholarships available to apply (2.7 status tracking)
    $activeScholarships = try_count($pdo, [
        ['sql' => 'SELECT COUNT(*) FROM scholarships WHERE status = "open"', 'params' => []]
    ]);

    // Pending reviews for this user
    $pendingReviews = try_count($pdo, [
        ['sql' => 'SELECT COUNT(*) FROM reviews WHERE student_id = :id AND status = "pending"', 'params' => [':id' => $studentId ?? 0]],
        ['sql' => 'SELECT COUNT(*) FROM reviews WHERE status = "pending"', 'params' => []]
    ]);

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
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard | Scholarship Management</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar"><?= strtoupper(substr(($user['first_name']??$user['username']),0,1)) ?></div>
        <div>
          <div class="welcome">Welcome,</div>
          <div class="username"><?= htmlspecialchars($user['first_name'] ?? $user['username']) ?></div>
        </div>
      </div>

      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Your Applications</a>
        <a href="apply_scholarship.php">Apply for Scholarship</a>
        <a href="notifications.php">Notifications</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>Dashboard</h2>
          <p>Welcome back! Here's your scholarship overview.</p>
        </div>
        <div>
          <div class="profile-info">
            <div class="avatar-big"><?= strtoupper(substr(($user['first_name']??$user['username']),0,1)) ?></div>
            <div style="text-align:right">
              <div style="font-weight:700"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></div>
              <div style="color:var(--muted)"><?= htmlspecialchars($user['email'] ?? '') ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); unset($_SESSION['db_error_shown']); ?></div>
      <?php endif; ?>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="value"><?= htmlspecialchars($applicationsCount) ?></div>
          <div class="label">Applications</div>
        </div>
        <div class="stat-card">
          <div class="value"><?= htmlspecialchars($activeScholarships) ?></div>
          <div class="label">Active Scholarships</div>
        </div>
        <div class="stat-card">
          <div class="value"><?= ($pendingReviews > 0) ? htmlspecialchars($pendingReviews) : 'No pending' ?></div>
          <div class="label">Pending Reviews</div>
        </div>
        <div class="stat-card">
          <div class="value"><?= htmlspecialchars($messagesCount) ?></div>
          <div class="label">Messages</div>
        </div>
      </div>

      <div class="panels-grid">
        <section class="panel">
          <h3>Today's Applications</h3>
          <p class="muted">You have <?= htmlspecialchars($applicationsCount) ?> total application(s) recorded.</p>
        </section>

        <section class="panel">
          <h3>Scholarship Status</h3>
          <p class="muted"><?php if ($activeScholarships > 0) { echo "There are $activeScholarships open scholarship(s) you can apply for."; } else { echo "No open scholarships at the moment. Check back later."; } ?></p>
        </section>

        <section class="panel quick-actions">
          <h3>Quick Actions</h3>
          <button class="btn" onclick="window.location.href='apply_scholarship.php'">Apply for Scholarship</button>
          <button class="btn secondary" onclick="window.location.href='applications.php'">View Applications</button>
        </section>
      </div>

    </main>
  </div>
</body>
</html>