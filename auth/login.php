<?php
/**
 * LOGIN PAGE
 * Role: All users (Admin, Staff, Student)
 */
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user']['role'] ?? 'student';
    switch ($role) {
        case 'admin': header("Location: ../admin/dashboard.php"); break;
        case 'staff':  header("Location: ../staff/dashboard.php"); break;
        default:       header("Location: ../students/dashboard.php"); break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - ScholarHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
    body { display: flex; height: 100vh; overflow: hidden; background: #fff; }

    .left-panel {
      width: 42%; flex-shrink: 0;
      background: linear-gradient(145deg, #E8192C 0%, #b0001a 100%);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 3rem 2.5rem; position: relative; overflow: hidden;
    }
    .left-panel::before {
      content: ''; position: absolute;
      width: 420px; height: 420px; border-radius: 50%;
      border: 60px solid rgba(255,255,255,0.07);
      top: 50%; left: 50%; transform: translate(-50%, -50%);
    }
    .left-panel::after {
      content: ''; position: absolute;
      width: 620px; height: 620px; border-radius: 50%;
      border: 60px solid rgba(255,255,255,0.05);
      top: 50%; left: 50%; transform: translate(-50%, -50%);
    }
    .left-content { position: relative; z-index: 1; text-align: center; width: 100%; max-width: 320px; }
    .brand-icon {
      width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.5rem; overflow: hidden;
    }
    .brand-icon img {
      width: 48px; height: 48px; object-fit: contain;
    }
      font-size: 2.25rem; margin: 0 auto 1.5rem; backdrop-filter: blur(4px);
    }
    .brand-name { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 0.75rem; }
    .brand-tagline { font-size: 0.9375rem; color: rgba(255,255,255,0.8); line-height: 1.6; margin-bottom: 2.5rem; }
    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .stat-box {
      background: rgba(255,255,255,0.15); border-radius: 12px;
      padding: 1rem 1.25rem; backdrop-filter: blur(4px);
      border: 1px solid rgba(255,255,255,0.2);
    }
    .stat-box-value { font-size: 1.375rem; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 0.25rem; }
    .stat-box-label { font-size: 0.75rem; color: rgba(255,255,255,0.75); font-weight: 500; }

    .right-panel {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 2rem; overflow-y: auto; background: #fff;
    }
    .form-box { width: 100%; max-width: 420px; }
    .form-title { font-size: 1.75rem; font-weight: 800; color: #1a1a2e; margin-bottom: 0.4rem; }
    .form-subtitle { font-size: 0.9rem; color: #888; margin-bottom: 2rem; }
    .field { margin-bottom: 1.25rem; }
    .field label { display: block; font-size: 0.875rem; font-weight: 600; color: #333; margin-bottom: 0.4rem; }
    .input-wrap { position: relative; }
    .input-icon {
      position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%);
      color: #bbb; font-size: 0.9rem; pointer-events: none;
    }
    .form-input {
      width: 100%; padding: 0.75rem 0.875rem 0.75rem 2.5rem;
      border: 1.5px solid #D1D5DB; border-radius: 10px;
      font-size: 0.9rem; font-family: inherit; color: #1a1a2e;
      background: #fafafa; transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-input:focus { outline: none; border-color: #E8192C; background: #fff; box-shadow: 0 0 0 3px rgba(232,25,44,0.08); }
    .form-input::placeholder { color: #bbb; }
    .toggle-pw {
      position: absolute; right: 0.875rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: #bbb; font-size: 0.9rem; padding: 0;
    }
    .toggle-pw:hover { color: #888; }
    .btn-submit {
      width: 100%; padding: 0.875rem; border-radius: 10px; border: none; cursor: pointer;
      font-size: 1rem; font-weight: 700; color: #fff; background: #E8192C;
      font-family: inherit; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 4px 16px rgba(232,25,44,0.3); margin-top: 0.5rem;
    }
    .btn-submit:hover { background: #c0001f; transform: translateY(-1px); }
    .form-footer { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: #888; }
    .form-footer a { color: #E8192C; font-weight: 600; text-decoration: none; }
    .form-footer a:hover { text-decoration: underline; }
    .back-link { display: block; text-align: center; margin-top: 1rem; font-size: 0.8125rem; color: #aaa; text-decoration: none; }
    .back-link:hover { color: #666; }
    .alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.8125rem; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .alert-success { background: #f0fdf4; color: #16a34a; border-color: #dcfce7; }

    @media (max-width: 768px) {
      body { flex-direction: column; overflow-y: auto; height: auto; }
      .left-panel { width: 100%; min-height: 40vh; padding: 2.5rem 1.5rem; }
      .right-panel { padding: 2rem 1.5rem; }
    }
  </style>
</head>
<body>

  <div class="left-panel">
    <div class="left-content">
      <div class="brand-icon">
        <img src="../assets/image/logo.svg" alt="ScholarHub Logo" style="width: 48px; height: 48px;">
      </div>
      <div class="brand-name">ScholarHub</div>
      <div class="brand-tagline">Your gateway to educational opportunities and scholarship success</div>
      <div class="stats-grid">
        <div class="stat-box"><div class="stat-box-value">2,400+</div><div class="stat-box-label">Active Scholarships</div></div>
        <div class="stat-box"><div class="stat-box-value">18,500+</div><div class="stat-box-label">Students Funded</div></div>
        <div class="stat-box"><div class="stat-box-value">340+</div><div class="stat-box-label">Partner Schools</div></div>
        <div class="stat-box"><div class="stat-box-value">$48M+</div><div class="stat-box-label">Total Disbursed</div></div>
      </div>
    </div>
  </div>

  <div class="right-panel">
    <div class="form-box">
      <h1 class="form-title">Welcome back</h1>
      <p class="form-subtitle">Sign in to continue your scholarship journey</p>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <form method="POST" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <div class="field">
          <label for="username">Email</label>
          <div class="input-wrap">
            <span class="input-icon"><i class="fas fa-envelope"></i></span>
            <input type="email" id="username" name="username" class="form-input" placeholder="e.g. juan@email.com" required autofocus>
          </div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon"><i class="fas fa-lock"></i></span>
            <input type="password" id="password" name="password" class="form-input" placeholder="Min. 8 characters" required>
            <button type="button" class="toggle-pw" onclick="togglePw(this)" data-target="password"><i class="fas fa-eye"></i></button>
          </div>
          <div style="text-align:right;margin-top:0.4rem;">
            <a href="forgot_password.php" style="font-size:0.8125rem;color:#E8192C;text-decoration:none;font-weight:500;">Forgot password?</a>
          </div>
        </div>

        <button type="submit" class="btn-submit">Sign In &rarr;</button>
      </form>

      <p class="form-footer">Don't have an account? <a href="register.php">Create one free</a></p>
      <a href="../index.php" class="back-link">Back to homepage</a>
    </div>
  </div>

  <script>
    function togglePw(btn) {
      const input = document.getElementById(btn.dataset.target);
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.innerHTML = input.type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    }
  </script>
</body>
</html>
