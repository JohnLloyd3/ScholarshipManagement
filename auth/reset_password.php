<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: ../member/dashboard.php");
    exit;
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
        <?php if (($_SESSION['pending_reset']['reset_type'] ?? 'secret') === 'email'): ?>
        <form class="auth-form-content" method="POST" action="../controllers/AuthController.php" autocomplete="off">
          <input type="hidden" name="action" value="reset_password_by_code">
          <div class="form-group">
            <label for="reset_code">Reset Code</label>
            <input id="reset_code" name="reset_code" type="text" placeholder="Enter the 6-digit code from your email" required autofocus maxlength="6" pattern="[0-9]{6}">
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
        <form class="auth-form-content" method="POST" action="../controllers/AuthController.php" autocomplete="off">
          <input type="hidden" name="action" value="reset_password">
          <div class="form-group">
            <label>Secret Question</label>
            <div style="padding: 10px 12px; border: 1px solid rgba(255,255,255,.12); border-radius: 10px; background: rgba(0,0,0,.08);">
              <?= htmlspecialchars($_SESSION['pending_reset']['secret_question'] ?? 'Secret question not set') ?>
            </div>
          </div>
          <div class="form-group">
            <label for="secret_answer">Secret Answer</label>
            <input id="secret_answer" name="secret_answer" type="text" placeholder="Enter your answer" required autofocus>
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
        <?php endif; ?>
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
