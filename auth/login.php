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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - ScholarHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/modern-theme.css">
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
    
    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: var(--space-xl) 0;
      color: var(--gray-500);
      font-size: 0.875rem;
    }
    
    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid var(--gray-200);
    }
    
    .divider span {
      padding: 0 var(--space-md);
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
</head>
<body>

  <div class="auth-container">
    <div class="auth-card fade-in">
      <div class="auth-logo">
        <div class="auth-logo-icon">🎓</div>
        <div class="auth-logo-text">ScholarHub</div>
      </div>
      
      <h2 class="auth-title">Welcome Back</h2>
      <p class="auth-subtitle">Sign in to continue your scholarship journey</p>
      
      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
          <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
      <?php endif; ?>
      
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-error">
          <?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="login">
        
        <div class="form-group">
          <label for="username" class="form-label">Username</label>
          <input 
            type="text" 
            id="username" 
            name="username" 
            class="form-input" 
            placeholder="Enter your username"
            required
            autofocus
          >
        </div>
        
        <div class="form-group">
          <label for="password" class="form-label">Password</label>
          <input 
            type="password" 
            id="password" 
            name="password" 
            class="form-input" 
            placeholder="Enter your password"
            required
          >
        </div>
        
        <div class="flex justify-between items-center" style="margin-bottom: var(--space-xl);">
          <label style="display: flex; align-items: center; gap: var(--space-sm); cursor: pointer;">
            <input type="checkbox" name="remember" style="width: 16px; height: 16px; cursor: pointer;">
            <span style="font-size: 0.9375rem; color: var(--gray-700);">Remember me</span>
          </label>
          <a href="forgot_password.php" style="font-size: 0.9375rem; font-weight: 500;">Forgot password?</a>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%;">
          Sign In
        </button>
      </form>
      
      <div class="divider">
        <span>or</span>
      </div>
      
      <a href="register.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
        Create New Account
      </a>
      
      <div class="auth-footer">
        <a href="../index.php">← Back to Home</a>
      </div>
    </div>
    
    <div style="text-align: center; margin-top: var(--space-xl); color: var(--gray-500); font-size: 0.875rem;">
      <p>© 2026 ScholarHub. All rights reserved.</p>
    </div>
  </div>

</body>
</html>