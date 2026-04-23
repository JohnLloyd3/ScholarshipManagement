<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

// Must be logged in to reach this page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo    = getPDO();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: change_password.php');
        exit;
    }

    $newPw      = $_POST['new_password'] ?? '';
    $confirmPw  = $_POST['confirm_password'] ?? '';

    if (strlen($newPw) < 8) {
        $_SESSION['flash'] = 'Password must be at least 8 characters.';
        header('Location: change_password.php');
        exit;
    }
    if ($newPw !== $confirmPw) {
        $_SESSION['flash'] = 'Passwords do not match.';
        header('Location: change_password.php');
        exit;
    }

    try {
        $hash = password_hash($newPw, PASSWORD_BCRYPT);
        // Update password and clear the force-change flag
        $pdo->prepare("UPDATE users SET password = :pw, must_change_password = 0 WHERE id = :id")
            ->execute([':pw' => $hash, ':id' => $userId]);
    } catch (Exception $e) {
        error_log('[change_password] ' . $e->getMessage());
    }

    $role = $_SESSION['user']['role'] ?? 'student';
    $_SESSION['success'] = 'Password changed successfully. Welcome!';
    switch ($role) {
        case 'admin': header('Location: ../admin/dashboard.php'); break;
        case 'staff': header('Location: ../staff/dashboard.php'); break;
        default:      header('Location: ../students/dashboard.php'); break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password - ScholarHub</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/modern-theme.css?v=20260418">
  <style>
    body { background: linear-gradient(135deg, var(--peach-ghost) 0%, var(--white) 50%, var(--peach-ghost) 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--space-xl); }
    .auth-card { background: var(--white); border-radius: var(--r-2xl); padding: var(--space-2xl); box-shadow: var(--shadow-xl); border: 1px solid var(--gray-200); max-width: 440px; width: 100%; }
  </style>
</head>
<body>
  <div class="auth-card fade-in">
    <div style="text-align:center;margin-bottom:var(--space-xl);">
      <div style="font-size:2.5rem;margin-bottom:var(--space-md);">??</div>
      <h2>Set Your New Password</h2>
    </div>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-danger" style="margin-bottom:var(--space-lg);"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      <div class="form-group">
        <label class="form-label">New Password <small>(min 8 characters)</small></label>
        <input type="password" name="new_password" class="form-input" required minlength="8" placeholder="Enter new password" autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-input" required minlength="8" placeholder="Repeat new password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:var(--space-lg);">Set Password & Continue</button>
    </form>
  </div>
</body>
</html>
