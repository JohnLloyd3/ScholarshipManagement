<?php
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();

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
            header("Location: ../students/dashboard.php");
            break;
    }
    exit;
}

if (isset($_SESSION['user_id'])) {
    redirectDashboardForRole();
}
?>
<?php
$page_title = 'Forgot Password - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
?>

<style>
  body {
    background: linear-gradient(135deg, var(--peach-ghost) 0%, var(--white) 50%, var(--peach-ghost) 100%);
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
    border-radius: var(--r-2xl);
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
    color: var(--peach);
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
    color: var(--peach);
    font-weight: 600;
  }
</style>

<div class="auth-container">
  <div class="auth-card fade-in">
    <div class="auth-logo">
      <div class="auth-logo-icon">??</div>
      <div class="auth-logo-text">ScholarHub</div>
    </div>
    
    <h2 class="auth-title">Forgot Password?</h2>
    <p class="auth-subtitle">Enter your username or email to reset your password</p>
    
    <?php if (!empty($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="../controllers/AuthController.php">
      <input type="hidden" name="action" value="request_password_reset">
      <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
      
      <div class="form-group">
        <label for="identifier" class="form-label">Username or Email</label>
        <input 
          type="text" 
          id="identifier" 
          name="identifier" 
          class="form-input" 
          placeholder="Enter your username or email"
          required
          autofocus
        >
        <p class="form-help">We'll send a 6-digit reset code to your email</p>
      </div>
      
      <button type="submit" class="btn btn-primary" style="width: 100%;">
        Send Reset Code
      </button>
    </form>
    
    <div class="auth-footer">
      <a href="login.php">? Back to Login</a>
    </div>
  </div>
  
  <div style="text-align: center; margin-top: var(--space-xl); color: var(--gray-500); font-size: 0.875rem;">
    <p>� 2026 ScholarHub. All rights reserved.</p>
  </div>
</div>

</body>
</html>
