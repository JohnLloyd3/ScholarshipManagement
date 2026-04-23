<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

// Already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../students/dashboard.php');
    exit;
}

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

if (!$token) {
    $error = 'Invalid verification link.';
} else {
    try {
        $pdo = getPDO();

        // Check activations table
        $userId = null;
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM activations WHERE token = :token LIMIT 1');
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $userId = (int)$row['user_id'];
                // Delete the token after successful verification
                $pdo->prepare('DELETE FROM activations WHERE token = :token')->execute([':token' => $token]);
            }
        } catch (Exception $e) {
            error_log('[verify_email] activations table error: ' . $e->getMessage());
        }

        // Fallback: try email_verifications table
        if (!$userId) {
            try {
                $stmt = $pdo->prepare('SELECT user_id FROM email_verifications WHERE token = :token AND expires_at > NOW() LIMIT 1');
                $stmt->execute([':token' => $token]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $userId = (int)$row['user_id'];
                    $pdo->prepare('DELETE FROM email_verifications WHERE token = :token')->execute([':token' => $token]);
                }
            } catch (Exception $e) {
                // Table may not exist
            }
        }

        // Fallback: check verification_token column on users table
        if (!$userId) {
            try {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE verification_token = :token LIMIT 1');
                $stmt->execute([':token' => $token]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $userId = (int)$row['id'];
                    $pdo->prepare('UPDATE users SET verification_token = NULL WHERE id = :id')->execute([':id' => $userId]);
                }
            } catch (Exception $e) { /* column may not exist */ }
        }

        if (!$userId) {
            $error = 'This verification link is invalid or has expired. Please register again or contact support.';
        } else {
            // Activate the account
            $pdo->prepare('UPDATE users SET email_verified = 1, active = 1 WHERE id = :id')
                ->execute([':id' => $userId]);
            $success = true;
        }
    } catch (Exception $e) {
        error_log('[verify_email] ' . $e->getMessage());
        $error = 'A system error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification - ScholarHub</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/modern-theme.css?v=20260418">
  <style>
    body { background: linear-gradient(135deg, var(--peach-ghost) 0%, var(--white) 50%, var(--peach-ghost) 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: var(--space-xl); }
    .auth-card { background: var(--white); border-radius: var(--r-2xl); padding: var(--space-2xl); box-shadow: var(--shadow-xl); border: 1px solid var(--gray-200); max-width: 480px; width: 100%; text-align: center; }
    .icon { font-size: 4rem; margin-bottom: var(--space-lg); }
  </style>
</head>
<body>
  <div class="auth-card fade-in">
    <?php if ($success): ?>
      <div class="icon">?</div>
      <h2>Email Verified!</h2>
      <p class="text-muted" style="margin: var(--space-lg) 0;">Your account has been activated. You can now log in.</p>
      <a href="login.php" class="btn btn-primary" style="width:100%;">Go to Login</a>
    <?php else: ?>
      <div class="icon">?</div>
      <h2>Verification Failed</h2>
      <p class="text-muted" style="margin: var(--space-lg) 0;"><?= htmlspecialchars($error) ?></p>
      <a href="register.php" class="btn btn-primary" style="width:100%;">Register Again</a>
      <div style="margin-top: var(--space-md);">
        <a href="login.php" class="text-muted">Back to Login</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
