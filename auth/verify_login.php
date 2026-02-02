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
  <title>Verify Login | Scholarship Management</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="auth-container" role="main">
    <div class="auth-card">
      <header class="logo-section">
        <h1><span class="logo-emoji">🎓</span> SMS</h1>
        <p>Enter verification code</p>
      </header>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <?php if (isset($_SESSION['pending_login'])): ?>
        <form class="auth-form-content" method="POST" action="../controllers/AuthController.php" autocomplete="off">
          <input type="hidden" name="action" value="verify_login">
          
          <div class="form-group">
            <label for="code">Verification Code</label>
            <input id="code" name="code" type="text" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}" required autofocus>
            <small>Check your email (<?= htmlspecialchars($_SESSION['pending_login']['email']) ?>) for the verification code.</small>
          </div>

          <button type="submit" class="submit-btn">Verify & Login</button>
        </form>
      <?php else: ?>
        <div class="flash error-flash">No pending login found. Please login first.</div>
        <div class="auth-links">
          <p><a href="login.php">Login</a></p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
