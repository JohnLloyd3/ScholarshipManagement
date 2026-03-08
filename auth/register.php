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
  <title>Register | Scholarship Management</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="style.css">
  <script src="../assets/register.js"></script>

</head>
<body>
  <main class="auth-container" role="main" aria-labelledby="registerTitle">
    <div class="auth-card">
      <header class="logo-section">
        <h1 id="registerTitle"><span class="logo-emoji">🎓</span> SMS</h1>
        <p>Create your scholarship account</p>
      </header>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <form class="auth-form-content" method="POST" action="../controllers/AuthController.php" autocomplete="off" novalidate>
        <input type="hidden" name="action" value="register">

        <div class="form-group">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" placeholder="Choose a username" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="Create a password" required>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label for="first_name">First Name</label>
            <input id="first_name" name="first_name" type="text" placeholder="First name" required>
          </div>
          <div class="form-group">
            <label for="last_name">Last Name</label>
            <input id="last_name" name="last_name" type="text" placeholder="Last name" required>
          </div>
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" placeholder="you@example.com" required>
        </div>

        <div class="form-group">
          <label for="phone">Phone Number</label>
          <input id="phone" name="phone" type="tel" placeholder="+63 912 345 6789">
        </div>

        <div class="form-group">
          <label for="address">Address</label>
          <textarea id="address" name="address" placeholder="Your address"></textarea>
        </div>

        <div class="form-group">
          <label for="role">Account Type</label>
          <select id="role" name="role" required readonly>
            <option value="student" selected>Student - Apply for scholarships</option>
          </select>
          <small>Only students can self-register. Staff accounts must be created by admin.</small>
        </div>

        <!-- Secret question/answer removed: using email-based resets instead -->

        <button type="submit" class="submit-btn">Create account</button>
      </form>

      <div class="auth-links">
        <p>Already have an account? <a href="login.php">Login</a></p>
      </div>
    </div>
  </main>
</body>
</html>