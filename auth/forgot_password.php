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
  <title>Forgot Password | Scholarship Management</title>
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

      <form class="auth-form-content" method="POST" action="../controllers/AuthController.php" autocomplete="off">
        <input type="hidden" name="action" value="request_password_reset">
        
        <div class="form-group">
          <label for="identifier">Username or Email</label>
          <input id="identifier" name="identifier" type="text" placeholder="username or you@email.com" required autofocus>
          <small>We will verify your identity via secret question or email code to reset your password.</small>
        </div>

        <button type="submit" class="submit-btn">Continue</button>
      </form>

      <div class="auth-links">
        <p><a href="login.php">Back to Login</a></p>
      </div>
    </div>
  </main>
</body>
</html>
