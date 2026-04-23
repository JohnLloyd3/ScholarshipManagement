<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireRole('admin', 'Admin access required');

$pdo = getPDO();
$csrf_token = generateCSRFToken();

// Ensure settings table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Load current settings
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT `value` FROM system_settings WHERE `key` = :k');
    $stmt->execute([':k' => $key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function saveSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare('INSERT INTO system_settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2, updated_at = NOW()')
        ->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: settings.php'); exit;
    }

    $section = $_POST['section'] ?? '';

    if ($section === 'smtp') {
        saveSetting($pdo, 'smtp_enabled',   $_POST['smtp_enabled'] ?? '0');
        saveSetting($pdo, 'smtp_host',      trim($_POST['smtp_host'] ?? ''));
        saveSetting($pdo, 'smtp_port',      trim($_POST['smtp_port'] ?? '587'));
        saveSetting($pdo, 'smtp_user',      trim($_POST['smtp_user'] ?? ''));
        // Only update password if provided (don't overwrite with blank)
        if (!empty($_POST['smtp_pass'])) {
            saveSetting($pdo, 'smtp_pass', trim($_POST['smtp_pass']));
        }
        saveSetting($pdo, 'email_from',      trim($_POST['email_from'] ?? ''));
        saveSetting($pdo, 'email_from_name', trim($_POST['email_from_name'] ?? 'ScholarHub'));
        $_SESSION['success'] = 'Email settings saved.';
    }

    if ($section === 'test_email') {
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = 'Invalid test email address.';
        } else {
            require_once __DIR__ . '/../config/email.php';
            $sent = sendEmail($to, 'ScholarHub — Test Email', '<h2>Test Email</h2><p>If you received this, your SMTP settings are working correctly.</p>', true);
            $_SESSION[$sent ? 'success' : 'flash'] = $sent ? "Test email sent to $to." : 'Failed to send test email. Check your SMTP settings and PHP error log.';
        }
    }

    header('Location: settings.php'); exit;
}

// Load values (DB first, then env fallback)
$smtpEnabled   = getSetting($pdo, 'smtp_enabled',   getenv('SMTP_ENABLED') ?: '0');
$smtpHost      = getSetting($pdo, 'smtp_host',      getenv('SMTP_HOST') ?: 'smtp.gmail.com');
$smtpPort      = getSetting($pdo, 'smtp_port',      getenv('SMTP_PORT') ?: '587');
$smtpUser      = getSetting($pdo, 'smtp_user',      getenv('SMTP_USER') ?: '');
$smtpPassSet   = getSetting($pdo, 'smtp_pass', '') !== '' || (getenv('SMTP_PASS') !== false && getenv('SMTP_PASS') !== '');
$emailFrom     = getSetting($pdo, 'email_from',      getenv('EMAIL_FROM') ?: '');
$emailFromName = getSetting($pdo, 'email_from_name', getenv('EMAIL_FROM_NAME') ?: 'ScholarHub');

$page_title = 'System Settings - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-cog"></i> System Settings</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- SMTP Settings -->
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-lg);"><i class="fas fa-envelope"></i> Email / SMTP Settings</h2>
  <p class="text-muted" style="margin-bottom:var(--space-xl);">
    Configure outgoing email. Use Gmail with an <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a>,
    or any SMTP provider. Settings saved here override environment variables.
  </p>

  <form method="POST">
    <input type="hidden" name="section" value="smtp">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:var(--space-sm);cursor:pointer;font-weight:600;">
        <input type="checkbox" name="smtp_enabled" value="1" <?= $smtpEnabled === '1' ? 'checked' : '' ?> style="width:18px;height:18px;">
        Enable SMTP (uncheck to use PHP mail() fallback)
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);">
      <div class="form-group">
        <label class="form-label">SMTP Host</label>
        <input type="text" name="smtp_host" class="form-input" value="<?= htmlspecialchars($smtpHost) ?>" placeholder="smtp.gmail.com">
        <small class="text-muted">Gmail: smtp.gmail.com | Outlook: smtp.office365.com</small>
      </div>
      <div class="form-group">
        <label class="form-label">SMTP Port</label>
        <input type="number" name="smtp_port" class="form-input" value="<?= htmlspecialchars($smtpPort) ?>" placeholder="587">
        <small class="text-muted">587 (TLS) or 465 (SSL)</small>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);">
      <div class="form-group">
        <label class="form-label">SMTP Username (Email)</label>
        <input type="email" name="smtp_user" class="form-input" value="<?= htmlspecialchars($smtpUser) ?>" placeholder="you@gmail.com">
      </div>
      <div class="form-group">
        <label class="form-label">SMTP Password / App Password</label>
        <input type="password" name="smtp_pass" class="form-input" placeholder="<?= $smtpPassSet ? '••••••••••••••• (saved)' : 'Enter password' ?>">
        <small class="text-muted">Leave blank to keep existing password. For Gmail, use an <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a>.</small>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);">
      <div class="form-group">
        <label class="form-label">From Email</label>
        <input type="email" name="email_from" class="form-input" value="<?= htmlspecialchars($emailFrom) ?>" placeholder="noreply@scholarhub.com">
        <small class="text-muted">Leave blank to use SMTP username</small>
      </div>
      <div class="form-group">
        <label class="form-label">From Name</label>
        <input type="text" name="email_from_name" class="form-input" value="<?= htmlspecialchars($emailFromName) ?>" placeholder="ScholarHub">
      </div>
    </div>

    <div style="margin-top:var(--space-lg);">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Email Settings</button>
    </div>
  </form>
</div>

<!-- Test Email -->
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <h2 style="margin-bottom:var(--space-lg);"><i class="fas fa-flask"></i> Send Test Email</h2>
  <p class="text-muted" style="margin-bottom:var(--space-lg);">Send a test email to verify your SMTP settings are working.</p>
  <form method="POST" style="display:flex;gap:var(--space-md);align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="section" value="test_email">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="form-group" style="margin:0;flex:1;min-width:250px;">
      <label class="form-label">Send test to</label>
      <input type="email" name="test_to" class="form-input" placeholder="your@email.com" required>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Test</button>
  </form>
</div>

<!-- Quick Setup Guide -->
<div class="content-card">
  <h2 style="margin-bottom:var(--space-lg);"><i class="fas fa-book"></i> Quick Setup Guide</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:var(--space-xl);">

    <div style="padding:var(--space-lg);background:var(--gray-50);border-radius:var(--r-lg);">
      <h4 style="margin-bottom:var(--space-md);">Gmail</h4>
      <ol style="margin:0;padding-left:var(--space-lg);color:var(--gray-700);line-height:2;">
        <li>Enable 2-Step Verification on your Google account</li>
        <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">App Passwords</a></li>
        <li>Create an app password for "Mail"</li>
        <li>Use that 16-character password above</li>
      </ol>
      <div style="margin-top:var(--space-md);background:var(--white);padding:var(--space-md);border-radius:var(--r-md);font-size:0.85rem;">
        Host: <strong>smtp.gmail.com</strong><br>
        Port: <strong>587</strong><br>
        User: <strong>your Gmail address</strong>
      </div>
    </div>

    <div style="padding:var(--space-lg);background:var(--gray-50);border-radius:var(--r-lg);">
      <h4 style="margin-bottom:var(--space-md);">Outlook / Office 365</h4>
      <ol style="margin:0;padding-left:var(--space-lg);color:var(--gray-700);line-height:2;">
        <li>Use your Microsoft account email and password</li>
        <li>Make sure SMTP AUTH is enabled in your account</li>
      </ol>
      <div style="margin-top:var(--space-md);background:var(--white);padding:var(--space-md);border-radius:var(--r-md);font-size:0.85rem;">
        Host: <strong>smtp.office365.com</strong><br>
        Port: <strong>587</strong><br>
        User: <strong>your Outlook address</strong>
      </div>
    </div>

    <div style="padding:var(--space-lg);background:var(--gray-50);border-radius:var(--r-lg);">
      <h4 style="margin-bottom:var(--space-md);">Yahoo Mail</h4>
      <ol style="margin:0;padding-left:var(--space-lg);color:var(--gray-700);line-height:2;">
        <li>Go to Yahoo Account Security</li>
        <li>Generate an App Password</li>
        <li>Use that password above</li>
      </ol>
      <div style="margin-top:var(--space-md);background:var(--white);padding:var(--space-md);border-radius:var(--r-md);font-size:0.85rem;">
        Host: <strong>smtp.mail.yahoo.com</strong><br>
        Port: <strong>587</strong><br>
        User: <strong>your Yahoo address</strong>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
