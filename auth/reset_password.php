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
<?php
$page_title = 'Reset Password - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
?>

<style>
  body {
    background: linear-gradient(135deg, var(--red-ghost) 0%, var(--white) 50%, var(--red-ghost) 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-xl);
  }
  
  .auth-container {
    width: 100%;
    max-width: 480px;
  }
  
  .auth-card {
    background: var(--white);
    border-radius: var(--radius-2xl);
    padding: var(--space-2xl);
    box-shadow: var(--shadow-xl);
    border: 1px solid var(--gray-200);
  }
  
  .auth-logo {
    text-align: center;
    margin-bottom: var(--space-2xl);
  }
  
  .auth-logo-icon {
    font-size: 3rem;
    margin-bottom: var(--space-md);
  }
  
  .auth-logo-text {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--red-primary);
    font-family: var(--font-display);
  }
  
  .auth-title {
    text-align: center;
    margin-bottom: var(--space-sm);
  }
  
  .auth-subtitle {
    text-align: center;
    color: var(--gray-600);
    margin-bottom: var(--space-2xl);
  }
  
  .auth-footer {
    text-align: center;
    margin-top: var(--space-xl);
    color: var(--gray-600);
  }
  
  .auth-footer a {
    color: var(--red-primary);
    font-weight: 600;
  }
</style>

<div class="auth-container">
  <div class="auth-card fade-in">
    <div class="auth-logo">
      <div class="auth-logo-icon">🔐</div>
      <div class="auth-logo-text">ScholarHub</div>
    </div>
    
    <h2 class="auth-title">Reset Password</h2>
    <p class="auth-subtitle">Enter the code from your email and create a new password</p>

    <?php if (!empty($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['pending_reset'])): ?>
      <form method="POST" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="reset_password_by_code">
        
        <div class="form-group">
          <label for="reset_code" class="form-label">Reset Code</label>
          <input 
            type="text" 
            id="reset_code" 
            name="reset_code" 
            class="form-input" 
            placeholder="Enter 6-digit code"
            required 
            autofocus 
            maxlength="6" 
            pattern="[0-9]{6}"
          >
          <p class="form-help">Check your email for the 6-digit reset code</p>
        </div>
        
        <div class="form-group">
          <label for="new_password" class="form-label">New Password</label>
          <input 
            type="password" 
            id="new_password" 
            name="new_password" 
            class="form-input" 
            placeholder="Enter new password"
            required 
            minlength="6"
          >
        </div>
        
        <div class="form-group">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <input 
            type="password" 
            id="confirm_password" 
            name="confirm_password" 
            class="form-input" 
            placeholder="Confirm new password"
            required 
            minlength="6"
          >
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%;">
          Reset Password
        </button>
      </form>
    <?php else: ?>
      <div class="alert alert-error">
        No pending password reset found. Please request a reset first.
      </div>
      <div class="auth-footer">
        <a href="forgot_password.php">Request Reset</a> | <a href="login.php">Login</a>
      </div>
    <?php endif; ?>
  </div>
  
  <div style="text-align: center; margin-top: var(--space-xl); color: var(--gray-500); font-size: 0.875rem;">
    <p>© 2026 ScholarHub. All rights reserved.</p>
  </div>
</div>

</body>
</html>
