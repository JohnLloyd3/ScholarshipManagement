<?php
require_once __DIR__ . '/../auth/helpers.php';
require_role(['staff','admin']);
require_once __DIR__ . '/../config/db.php';

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

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
