<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/FraudDetectionHelper.php';

requireLogin();
requireAnyRole(['admin', 'staff'], 'Admin or Staff access required');

$pdo = getPDO();
$user = $_SESSION['user'] ?? [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'resolve_alert') {
        $alertId = (int)($_POST['alert_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $notes = trim($_POST['resolution_notes'] ?? '');
        
        if ($alertId && in_array($status, ['resolved', 'false_positive'])) {
            $stmt = $pdo->prepare('
                UPDATE fraud_alerts 
                SET status = :status, resolved_by = :resolved_by, resolved_at = NOW(), resolution_notes = :notes
                WHERE id = :id
            ');
            $stmt->execute([
                ':status' => $status,
                ':resolved_by' => $_SESSION['user_id'],
                ':notes' => $notes,
                ':id' => $alertId
            ]);
            
            $_SESSION['success'] = 'Alert marked as ' . $status . '.';
        }
    }
    
    if ($action === 'run_fraud_check') {
        $appId = (int)($_POST['application_id'] ?? 0);
        if ($appId) {
            $result = runFraudDetection($pdo, $appId);
            $_SESSION['success'] = 'Fraud check completed. Found ' . count($result['alerts']) . ' alert(s). Fraud score: ' . $result['fraud_score'];
        }
    }
    
    if ($action === 'run_bulk_check') {
        // Run fraud detection on all applications
        $stmt = $pdo->query('SELECT id FROM applications WHERE status NOT IN ("draft", "withdrawn")');
        $apps = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $totalAlerts = 0;
        foreach ($apps as $appId) {
            $result = runFraudDetection($pdo, $appId);
            $totalAlerts += count($result['alerts']);
        }
        
        $_SESSION['success'] = 'Bulk fraud check completed on ' . count($apps) . ' applications. Found ' . $totalAlerts . ' alert(s).';
    }
    
    header('Location: fraud_detection.php');
    exit;
}

// Get fraud statistics
$stats = getFraudStatistics($pdo);

// Get all fraud alerts
$filter = $_GET['filter'] ?? 'pending';
$severity = $_GET['severity'] ?? '';

$sql = '
    SELECT 
        f.*,
        u.first_name, u.last_name, u.email,
        a.id as app_id,
        s.title as scholarship_title,
        resolver.first_name as resolved_by_name
    FROM fraud_alerts f
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN applications a ON f.application_id = a.id
    LEFT JOIN scholarships s ON a.scholarship_id = s.id
    LEFT JOIN users resolver ON f.resolved_by = resolver.id
    WHERE 1=1
';

$params = [];
if ($filter && $filter !== 'all') {
    $sql .= ' AND f.status = :status';
    $params[':status'] = $filter;
}
if ($severity) {
    $sql .= ' AND f.severity = :severity';
    $params[':severity'] = $severity;
}

$sql .= ' ORDER BY f.created_at DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get high-risk applications
$highRiskStmt = $pdo->query('
    SELECT 
        a.*,
        u.first_name, u.last_name, u.email,
        s.title as scholarship_title
    FROM applications a
    JOIN users u ON a.user_id = u.id
    JOIN scholarships s ON a.scholarship_id = s.id
    WHERE a.fraud_score > 50
    ORDER BY a.fraud_score DESC
    LIMIT 20
');
$highRiskApps = $highRiskStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Fraud Detection - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🛡️ Fraud Detection System</h1>
  <p class="text-muted">Monitor and investigate suspicious activities</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<!-- Statistics Dashboard -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-lg); margin-bottom: var(--space-xl);">
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_status']['pending'] ?? 0) ?></div>
    <div class="stat-label">Pending Alerts</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_severity']['critical'] ?? 0) ?></div>
    <div class="stat-label">Critical Alerts</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['high_risk_apps'] ?? 0) ?></div>
    <div class="stat-label">High-Risk Apps</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_status']['resolved'] ?? 0) ?></div>
    <div class="stat-label">Resolved</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_type']['duplicate_document'] ?? 0) ?></div>
    <div class="stat-label">Duplicate Docs</div>
  </div>
</div>

<!-- Actions -->
<div class="content-card" style="margin-bottom: var(--space-xl);">
  <h2>⚡ Quick Actions</h2>
  <div style="display: flex; gap: var(--space-md); flex-wrap: wrap;">
    <form method="POST" style="display: inline;">
      <input type="hidden" name="action" value="run_bulk_check">
      <button type="submit" class="btn btn-primary" onclick="return confirm('Run fraud detection on all applications? This may take a while.')">
        🔍 Run Bulk Fraud Check
      </button>
    </form>
    <a href="?filter=pending" class="btn btn-ghost">📋 View Pending Alerts</a>
    <a href="?severity=critical" class="btn btn-ghost">⚠️ Critical Only</a>
    <a href="?filter=all" class="btn btn-ghost">📊 View All</a>
  </div>
</div>

<!-- High-Risk Applications -->
<?php if (!empty($highRiskApps)): ?>
<div class="content-card" style="margin-bottom: var(--space-xl);">
  <h2>⚠️ High-Risk Applications (Score > 50)</h2>
  <table class="modern-table">
    <thead>
      <tr>
        <th>Applicant</th>
        <th>Scholarship</th>
        <th>Fraud Score</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($highRiskApps as $app): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?></strong><br>
            <small class="text-muted"><?= htmlspecialchars($app['email'] ?? '') ?></small>
          </td>
          <td><?= htmlspecialchars($app['scholarship_title'] ?? 'N/A') ?></td>
          <td>
            <span class="status-badge status-rejected" style="font-weight: bold;">
              <?= number_format($app['fraud_score'], 1) ?>
            </span>
          </td>
          <td>
            <span class="status-badge status-<?= strtolower($app['status']) ?>">
              <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
            </span>
          </td>
          <td>
            <a href="../staff/application_view.php?id=<?= (int)$app['id'] ?>" class="btn btn-ghost btn-sm">👁️ View</a>
            <form method="POST" style="display: inline;">
              <input type="hidden" name="action" value="run_fraud_check">
              <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm">🔍 Recheck</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Fraud Alerts -->
<div class="content-card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
    <h2>🚨 Fraud Alerts</h2>
    <div style="display: flex; gap: var(--space-sm);">
      <select onchange="window.location.href='?filter='+this.value+'&severity=<?= htmlspecialchars($severity) ?>'" class="form-input">
        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Status</option>
        <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="investigating" <?= $filter === 'investigating' ? 'selected' : '' ?>>Investigating</option>
        <option value="resolved" <?= $filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
        <option value="false_positive" <?= $filter === 'false_positive' ? 'selected' : '' ?>>False Positive</option>
      </select>
      <select onchange="window.location.href='?filter=<?= htmlspecialchars($filter) ?>&severity='+this.value" class="form-input">
        <option value="">All Severity</option>
        <option value="critical" <?= $severity === 'critical' ? 'selected' : '' ?>>Critical</option>
        <option value="high" <?= $severity === 'high' ? 'selected' : '' ?>>High</option>
        <option value="medium" <?= $severity === 'medium' ? 'selected' : '' ?>>Medium</option>
        <option value="low" <?= $severity === 'low' ? 'selected' : '' ?>>Low</option>
      </select>
    </div>
  </div>

  <?php if (!empty($alerts)): ?>
    <table class="modern-table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Severity</th>
          <th>Applicant</th>
          <th>Description</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($alerts as $alert): ?>
          <tr>
            <td>
              <span class="status-badge status-<?= $alert['alert_type'] ?>">
                <?= ucfirst(str_replace('_', ' ', $alert['alert_type'])) ?>
              </span>
            </td>
            <td>
              <span class="status-badge status-<?= $alert['severity'] === 'critical' ? 'rejected' : $alert['severity'] ?>">
                <?= ucfirst($alert['severity']) ?>
              </span>
            </td>
            <td>
              <strong><?= htmlspecialchars(($alert['first_name'] ?? '') . ' ' . ($alert['last_name'] ?? '')) ?></strong><br>
              <small class="text-muted"><?= htmlspecialchars($alert['email'] ?? '') ?></small>
            </td>
            <td>
              <?= htmlspecialchars($alert['description']) ?>
              <?php if ($alert['scholarship_title']): ?>
                <br><small class="text-muted">Scholarship: <?= htmlspecialchars($alert['scholarship_title']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <span class="status-badge status-<?= $alert['status'] ?>">
                <?= ucfirst(str_replace('_', ' ', $alert['status'])) ?>
              </span>
            </td>
            <td><small><?= date('M d, Y', strtotime($alert['created_at'])) ?></small></td>
            <td>
              <?php if ($alert['application_id']): ?>
                <a href="../staff/application_view.php?id=<?= (int)$alert['application_id'] ?>" class="btn btn-ghost btn-sm">👁️ View</a>
              <?php endif; ?>
              <?php if ($alert['status'] === 'pending'): ?>
                <button onclick="resolveAlert(<?= (int)$alert['id'] ?>)" class="btn btn-ghost btn-sm">✓ Resolve</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">🛡️</div>
      <h3 class="empty-state-title">No Alerts Found</h3>
      <p class="empty-state-description">No fraud alerts match your filters.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Resolve Alert Modal -->
<div id="resolveModal" class="modal">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h2>✓ Resolve Alert</h2>
      <span class="modal-close" onclick="document.getElementById('resolveModal').style.display='none'">&times;</span>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="resolve_alert">
      <input type="hidden" name="alert_id" id="resolveAlertId">
      
      <div class="form-group">
        <label>Resolution Status *</label>
        <select name="status" class="form-input" required>
          <option value="resolved">Resolved (Confirmed Fraud)</option>
          <option value="false_positive">False Positive (Not Fraud)</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Resolution Notes</label>
        <textarea name="resolution_notes" class="form-input" rows="4" placeholder="Add notes about your investigation..."></textarea>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('resolveModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Resolution</button>
      </div>
    </form>
  </div>
</div>

<script>
function resolveAlert(alertId) {
  document.getElementById('resolveAlertId').value = alertId;
  document.getElementById('resolveModal').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
