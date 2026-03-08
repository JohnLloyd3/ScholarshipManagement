<?php
session_start();

function redirectDashboardForRole()
{
    $role = $_SESSION['user']['role'] ?? 'student';
    switch ($role) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'staff':
            header("Location: ../staff/dashboard.php");
            break;
        default:
            header("Location: ../member/dashboard.php");
            break;
    }
    exit;
}

if (isset($_SESSION['user_id'])) {
    redirectDashboardForRole();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Password | Scholarship Management</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="auth-container" role="main">
    <div class="auth-card">
      <header class="logo-section">
        <h1><span class="logo-emoji">🎓</span> SMS</h1>
        <p>Reset your password</p>
      </header>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <?php if (isset($_SESSION['pending_reset'])): ?>
        <form class="auth-form-content" method="POST" action="../controllers/AuthController.php" autocomplete="off">
          <input type="hidden" name="action" value="reset_password_by_code">
          <div class="form-group">
            <label for="reset_code">Reset Code</label>
            <input id="reset_code" name="reset_code" type="text" placeholder="Enter the 6-digit code from your email" required autofocus maxlength="6" pattern="[0-9]{6}">
            <small>We sent a 6-digit code to the email on file for your account.</small>
          </div>
          <div class="form-group">
            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" placeholder="Enter new password" required minlength="6">
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" name="confirm_password" type="password" placeholder="Confirm new password" required minlength="6">
          </div>
          <button type="submit" class="submit-btn">Reset Password</button>
        </form>
      <?php else: ?>
        <div class="flash error-flash">No pending password reset found. Please request a reset first.</div>
        <div class="auth-links">
          <p><a href="forgot_password.php">Request Reset</a> | <a href="login.php">Login</a></p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
