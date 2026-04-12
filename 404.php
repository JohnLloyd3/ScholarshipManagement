<?php
require_once __DIR__ . '/helpers/SecurityHelper.php';
startSecureSession();
http_response_code(404);
$base_path = './';
$page_title = '404 - Page Not Found | ScholarHub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/modern-theme.css">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--gray-50); }
    .error-card { text-align: center; background: var(--white); border-radius: var(--radius-2xl); padding: var(--space-2xl); box-shadow: var(--shadow-xl); max-width: 480px; width: 100%; }
    .error-code { font-size: 6rem; font-weight: 800; color: var(--red-primary); line-height: 1; margin-bottom: var(--space-md); }
  </style>
</head>
<body>
  <div class="error-card fade-in">
    <div class="error-code">404</div>
    <h2 style="margin-bottom:var(--space-md);">Page Not Found</h2>
    <p class="text-muted" style="margin-bottom:var(--space-xl);">The page you're looking for doesn't exist or has been moved.</p>
    <div style="display:flex;gap:var(--space-md);justify-content:center;flex-wrap:wrap;">
      <a href="javascript:history.back()" class="btn btn-ghost">← Go Back</a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php
          $role = $_SESSION['user']['role'] ?? 'student';
          $dash = match($role) { 'admin' => 'admin/dashboard.php', 'staff' => 'staff/dashboard.php', default => 'member/dashboard.php' };
        ?>
        <a href="<?= $dash ?>" class="btn btn-primary">🏠 Dashboard</a>
      <?php else: ?>
        <a href="index.php" class="btn btn-primary">🏠 Home</a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
