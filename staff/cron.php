<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

$pdo = getPDO();

// Ensure cron_runs table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS cron_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  script VARCHAR(255),
  ran_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(32),
  output TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$cronDir = realpath(__DIR__ . '/../cron');
$files = [];
if ($cronDir && is_dir($cronDir)) {
    foreach (scandir($cronDir) as $f) {
        if (in_array($f, ['.','..'])) continue;
        if (pathinfo($f, PATHINFO_EXTENSION) !== 'php') continue;
        $files[] = $f;
    }
}

// Handle run request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['run_script'])) {
    $script = basename($_POST['run_script']);
    $scriptPath = $cronDir . DIRECTORY_SEPARATOR . $script;
    if (!is_file($scriptPath)) {
        $message = 'Script not found.';
    } else {
        $output = '';
        $status = 'error';
        // Try shell execution first
        $phpBin = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($scriptPath) . ' 2>&1';
        $shellOut = null;
        if (function_exists('shell_exec')) {
            try {
                $shellOut = shell_exec($cmd);
            } catch (Throwable $e) {
                $shellOut = null;
            }
        }

        if ($shellOut !== null) {
            $output = $shellOut;
            $status = 'ok';
        } else {
            // Fallback to include the script and capture output
            try {
                ob_start();
                include $scriptPath;
                $output = ob_get_clean();
                $status = 'ok';
            } catch (Throwable $e) {
                $output = 'Exception: ' . $e->getMessage();
                $status = 'error';
            }
        }

        // record run
        $stmt = $pdo->prepare('INSERT INTO cron_runs (name, script, ran_at, status, output) VALUES (:name, :script, NOW(), :status, :output)');
        $stmt->execute([':name'=>$script, ':script'=>$script, ':status'=>$status, ':output'=>$output]);
        $message = 'Script executed. Status: ' . $status;
    }
}

// Fetch last runs
$lastRuns = [];
$stmt = $pdo->query('SELECT script, MAX(ran_at) as last_run, status FROM cron_runs GROUP BY script');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $lastRuns[$r['script']] = $r;

// Auto-trigger auto_close_scholarships if not run in last hour
$autoCloseScript = $cronDir . DIRECTORY_SEPARATOR . 'auto_close_scholarships.php';
if (is_file($autoCloseScript)) {
    $lastAutoClose = $lastRuns['auto_close_scholarships.php']['last_run'] ?? null;
    if (!$lastAutoClose || (time() - strtotime($lastAutoClose)) > 3600) {
        try {
            ob_start();
            include $autoCloseScript;
            $out = ob_get_clean();
            $stmt = $pdo->prepare('INSERT INTO cron_runs (name, script, ran_at, status, output) VALUES (:name, :script, NOW(), :status, :output)');
            $stmt->execute([':name'=>'auto_close_scholarships.php', ':script'=>'auto_close_scholarships.php', ':status'=>'ok', ':output'=>$out]);
        } catch (Throwable $e) { /* silent */ }
    }
}

?>
<?php
$page_title = 'Cron Jobs - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>⚙️ Automation / Cron Control</h1>
  <p class="text-muted">Manage and execute automated tasks</p>
</div>

<?php if ($message): ?>
  <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="content-card">
  <p class="text-muted" style="margin-bottom:var(--space-xl)">List of cron scripts in the `cron/` folder. Use the Run button to trigger them now. Runs are recorded in `cron_runs`.</p>
  <table class="modern-table">
    <thead><tr><th>Script</th><th>Last Run</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f) ?></td>
        <td><small><?= htmlspecialchars($lastRuns[$f]['last_run'] ?? '—') ?></small></td>
        <td><span class="status-badge status-<?= strtolower($lastRuns[$f]['status'] ?? 'pending') ?>"><?= htmlspecialchars($lastRuns[$f]['status'] ?? '—') ?></span></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="run_script" value="<?= htmlspecialchars($f) ?>">
            <button class="btn btn-primary btn-sm" type="submit">▶️ Run</button>
          </form>
          <a class="btn btn-ghost btn-sm" href="cron_log.php?script=<?= urlencode($f) ?>">📜 View Logs</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="content-card" style="margin-top:var(--space-xl);">
  <h3 style="margin-bottom:var(--space-lg);">⏰ Automated Scheduling Setup</h3>
  <p class="text-muted" style="margin-bottom:var(--space-lg);">To run these scripts automatically, set up a scheduled task on your server.</p>

  <h4 style="margin-bottom:var(--space-md);">Linux / cPanel (crontab)</h4>
  <pre style="background:var(--gray-900);color:#e2e8f0;padding:var(--space-lg);border-radius:var(--radius-lg);overflow-x:auto;font-size:0.85rem;margin-bottom:var(--space-xl);"># Run every hour
0 * * * * /usr/bin/php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/auto_close_scholarships.php')) ?> >> /tmp/cron_close.log 2>&1

# Run daily at midnight
0 0 * * * /usr/bin/php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/auto_archive_scholarships.php')) ?> >> /tmp/cron_archive.log 2>&1

# Send deadline reminders daily at 8am
0 8 * * * /usr/bin/php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/send_deadline_reminders.php')) ?> >> /tmp/cron_reminders.log 2>&1

# Process email queue every 5 minutes
*/5 * * * * /usr/bin/php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/process_email_queue.php')) ?> >> /tmp/cron_email.log 2>&1</pre>

  <h4 style="margin-bottom:var(--space-md);">Windows (Task Scheduler)</h4>
  <pre style="background:var(--gray-900);color:#e2e8f0;padding:var(--space-lg);border-radius:var(--radius-lg);overflow-x:auto;font-size:0.85rem;"># Open PowerShell as Administrator and run:

# Auto-close scholarships (every hour)
schtasks /create /tn "ScholarHub_AutoClose" /tr "C:\xampp\php\php.exe <?= str_replace('/', '\\', realpath(__DIR__ . '/../cron/auto_close_scholarships.php')) ?>" /sc hourly /f

# Archive scholarships (daily midnight)
schtasks /create /tn "ScholarHub_Archive" /tr "C:\xampp\php\php.exe <?= str_replace('/', '\\', realpath(__DIR__ . '/../cron/auto_archive_scholarships.php')) ?>" /sc daily /st 00:00 /f

# Deadline reminders (daily 8am)
schtasks /create /tn "ScholarHub_Reminders" /tr "C:\xampp\php\php.exe <?= str_replace('/', '\\', realpath(__DIR__ . '/../cron/send_deadline_reminders.php')) ?>" /sc daily /st 08:00 /f

# Email queue (every 5 minutes)
schtasks /create /tn "ScholarHub_EmailQueue" /tr "C:\xampp\php\php.exe <?= str_replace('/', '\\', realpath(__DIR__ . '/../cron/process_email_queue.php')) ?>" /sc minute /mo 5 /f</pre>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
