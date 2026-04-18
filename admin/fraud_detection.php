<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
require_once __DIR__ . '/../helpers/FraudDetectionHelper.php';

requireLogin();
requireAnyRole(['admin'], 'Admin access required');

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$csrf_token = generateCSRFToken();

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: fraud_detection.php');
        exit;
    }

    $action  = $_POST['action'] ?? '';
    $alertId = (int)($_POST['alert_id'] ?? 0);

    if (in_array($action, ['review_alert', 'dismiss_alert']) && $alertId) {
        // Check current status
        $chk = $pdo->prepare('SELECT status FROM fraud_alerts WHERE id = :id');
        $chk->execute([':id' => $alertId]);
        $current = $chk->fetchColumn();

        if (!$current) {
            $_SESSION['flash'] = 'Alert not found.';
        } elseif (in_array($current, ['reviewed', 'dismissed'])) {
            $_SESSION['flash'] = 'Alert has already been reviewed.';
        } else {
            $newStatus = ($action === 'review_alert') ? 'reviewed' : 'dismissed';
            $pdo->prepare('UPDATE fraud_alerts SET status = :s, reviewed_by = :by, reviewed_at = NOW() WHERE id = :id')
              ->execute([':s' => $newStatus, ':by' => $userId, ':id' => $alertId]);
            $_SESSION['success'] = 'Alert marked as ' . $newStatus . '.';
        }
    }

    if ($action === 'run_fraud_check') {
        $appId = (int)($_POST['application_id'] ?? 0);
        if ($appId) {
            $result = runFraudDetection($pdo, $appId);
            $_SESSION['success'] = 'Fraud check completed. Score: ' . $result['fraud_score'];
        }
    }

    header('Location: fraud_detection.php');
    exit;
}

// ── Detail view ───────────────────────────────────────────────────────────────
$viewId = isset($_GET['view']) && $_GET['view'] === 'detail' ? (int)($_GET['id'] ?? 0) : 0;
if ($viewId) {
    $stmt = $pdo->prepare('
        SELECT f.*, u.first_name, u.last_name, u.email,
               r.first_name AS reviewer_first, r.last_name AS reviewer_last,
               a.fraud_score
        FROM fraud_alerts f
        LEFT JOIN users u ON f.user_id = u.id
        LEFT JOIN users r ON f.reviewed_by = r.id
        LEFT JOIN applications a ON f.application_id = a.id
        WHERE f.id = :id
    ');
    $stmt->execute([':id' => $viewId]);
    $alert = $stmt->fetch(PDO::FETCH_ASSOC);

    $page_title = 'Fraud Alert Detail - ScholarHub';
    $base_path  = '../';
    require_once __DIR__ . '/../includes/modern-header.php';
    require_once __DIR__ . '/../includes/modern-sidebar.php';

    if (!$alert) {
        echo '<div class="content-card"><p class="text-muted">Alert not found.</p><a href="fraud_detection.php" class="btn btn-ghost">← Back</a></div>';
        require_once __DIR__ . '/../includes/modern-footer.php';
        exit;
    }

    $evidence = [];
    if ($alert['evidence']) {
        $evidence = json_decode($alert['evidence'], true) ?: [];
    }
    ?>
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <h1>🚨 Alert #<?= $viewId ?></h1>
        <p class="text-muted"><?= ucfirst(str_replace('_', ' ', $alert['alert_type'])) ?></p>
      </div>
      <a href="fraud_detection.php" class="btn btn-ghost">← Back to Alerts</a>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
    <?php endif; ?>

    <div class="content-card" style="margin-bottom:var(--space-xl);">
      <h2>Alert Details</h2>
      <table style="width:100%;border-collapse:collapse;margin-top:var(--space-lg);">
        <?php $rows = [
          'Type'         => ucfirst(str_replace('_', ' ', $alert['alert_type'])),
          'Severity'     => ucfirst($alert['severity']),
          'Status'       => ucfirst($alert['status']),
          'Description'  => htmlspecialchars($alert['description']),
          'Applicant'    => htmlspecialchars(($alert['first_name'] ?? '') . ' ' . ($alert['last_name'] ?? '') . ' (' . ($alert['email'] ?? '') . ')'),
          'Application'  => $alert['application_id'] ? '<a href="../staff/application_view.php?id=' . (int)$alert['application_id'] . '">#' . (int)$alert['application_id'] . '</a>' : '—',
          'Document ID'  => $alert['document_id'] ? '#' . (int)$alert['document_id'] : '—',
          'Fraud Score'  => $alert['fraud_score'] !== null ? number_format((float)$alert['fraud_score'], 1) : '—',
          'Created At'   => date('M d, Y H:i', strtotime($alert['created_at'])),
          'Reviewed By'  => $alert['reviewed_by'] ? htmlspecialchars(($alert['reviewer_first'] ?? '') . ' ' . ($alert['reviewer_last'] ?? '')) : '—',
          'Reviewed At'  => $alert['reviewed_at'] ? date('M d, Y H:i', strtotime($alert['reviewed_at'])) : '—',
        ]; ?>
        <?php foreach ($rows as $label => $val): ?>
          <tr style="border-bottom:1px solid var(--gray-200);">
            <td style="padding:var(--space-sm) var(--space-md);font-weight:600;width:160px;color:var(--gray-600);"><?= $label ?></td>
            <td style="padding:var(--space-sm) var(--space-md);"><?= $val ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <?php if (!empty($evidence)): ?>
    <div class="content-card" style="margin-bottom:var(--space-xl);">
      <h2>Evidence</h2>
      <pre style="background:var(--gray-50);padding:var(--space-lg);border-radius:var(--r-md);overflow:auto;font-size:0.85rem;"><?= htmlspecialchars(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
    <?php endif; ?>

    <?php if ($alert['status'] === 'pending'): ?>
    <div class="content-card">
      <h2>Actions</h2>
      <div style="display:flex;gap:var(--space-md);margin-top:var(--space-lg);">
        <form method="POST">
          <input type="hidden" name="action" value="review_alert">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="alert_id" value="<?= $viewId ?>">
          <button type="submit" class="btn btn-primary" onclick="return confirm('Mark this alert as reviewed?')">✅ Mark Reviewed</button>
        </form>
        <form method="POST">
          <input type="hidden" name="action" value="dismiss_alert">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="alert_id" value="<?= $viewId ?>">
          <button type="submit" class="btn btn-ghost" onclick="return confirm('Dismiss this alert as false positive?')">❌ Dismiss</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php
    require_once __DIR__ . '/../includes/modern-footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────────────────────────
$stats = getFraudStatistics($pdo);

$filterStatus   = $_GET['status']   ?? 'pending';
$filterSeverity = $_GET['severity'] ?? '';
$filterType     = $_GET['type']     ?? '';

$where  = ['1=1'];
$params = [];
if ($filterStatus && $filterStatus !== 'all') {
    $where[] = 'f.status = :status';
    $params[':status'] = $filterStatus;
}
if ($filterSeverity) {
    $where[] = 'f.severity = :severity';
    $params[':severity'] = $filterSeverity;
}
if ($filterType) {
    $where[] = 'f.alert_type = :atype';
    $params[':atype'] = $filterType;
}

$sql = 'SELECT f.*, u.first_name, u.last_name, u.email, a.id AS app_id, s.title AS scholarship_title
        FROM fraud_alerts f
        LEFT JOIN users u ON f.user_id = u.id
        LEFT JOIN applications a ON f.application_id = a.id
        LEFT JOIN scholarships s ON a.scholarship_id = s.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY f.created_at DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Fraud Detection - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🛡️ Fraud Detection</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:var(--space-xl);">
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_status']['pending'] ?? 0) ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_status']['reviewed'] ?? 0) ?></div>
    <div class="stat-label">Reviewed</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_status']['dismissed'] ?? 0) ?></div>
    <div class="stat-label">Dismissed</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_severity']['low'] ?? 0) ?></div>
    <div class="stat-label">Low (0–30)</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)($stats['by_severity']['medium'] ?? 0) ?></div>
    <div class="stat-label">Medium (31–60)</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= (int)(($stats['by_severity']['high'] ?? 0) + ($stats['by_severity']['critical'] ?? 0)) ?></div>
    <div class="stat-label">High (61–100)</div>
  </div>
</div>

<!-- Filters -->
<div class="content-card" style="margin-bottom:var(--space-xl);">
  <form method="GET" style="display:flex;flex-wrap:wrap;gap:var(--space-md);align-items:flex-end;">
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Status</label>
      <select name="status" class="form-input">
        <option value="all" <?= $filterStatus==='all'?'selected':'' ?>>All</option>
        <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
        <option value="reviewed" <?= $filterStatus==='reviewed'?'selected':'' ?>>Reviewed</option>
        <option value="dismissed" <?= $filterStatus==='dismissed'?'selected':'' ?>>Dismissed</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:140px;">
      <label>Severity</label>
      <select name="severity" class="form-input">
        <option value="">All</option>
        <?php foreach(['high','medium','low'] as $sev): ?>
          <option value="<?= $sev ?>" <?= $filterSeverity===$sev?'selected':'' ?>><?= ucfirst($sev) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:160px;">
      <label>Type</label>
      <select name="type" class="form-input">
        <option value="">All</option>
        <?php foreach(['duplicate_application','duplicate_document','suspicious_income','multiple_accounts'] as $t): ?>
          <option value="<?= $t ?>" <?= $filterType===$t?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$t)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="fraud_detection.php" class="btn btn-ghost">Clear</a>
  </form>
</div>

<!-- Alert Table -->
<div class="content-card">
  <h2>🚨 Fraud Alerts</h2>
  <?php if (!empty($alerts)): ?>
    <table class="modern-table" style="margin-top:var(--space-lg);">
      <thead>
        <tr><th>ID</th><th>Type</th><th>Severity</th><th>Applicant</th><th>Application</th><th>Description</th><th>Status</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach($alerts as $a): ?>
          <tr>
            <td><?= (int)$a['id'] ?></td>
            <td><span class="status-badge"><?= ucfirst(str_replace('_',' ',$a['alert_type'])) ?></span></td>
            <td>
              <?php $sevColor = ['critical'=>'#dc2626','high'=>'#ea580c','medium'=>'#d97706','low'=>'#16a34a'][$a['severity']] ?? '#6b7280'; ?>
              <span style="color:<?= $sevColor ?>;font-weight:600;"><?= ucfirst($a['severity']) ?></span>
            </td>
            <td>
              <strong><?= htmlspecialchars(($a['first_name']??'').' '.($a['last_name']??'')) ?></strong>
            </td>
            <td><?= $a['application_id'] ? '<a href="../staff/application_view.php?id='.(int)$a['application_id'].'">#'.(int)$a['application_id'].'</a>' : '—' ?></td>
            <td style="max-width:260px;"><?= htmlspecialchars(mb_strimwidth($a['description'],0,80,'…')) ?></td>
            <td><span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
            <td><small><?= date('M d, Y', strtotime($a['created_at'])) ?></small></td>
            <td>
              <a href="fraud_detection.php?view=detail&id=<?= (int)$a['id'] ?>" class="btn btn-ghost btn-sm">👁️</a>
              <?php if ($a['status'] === 'pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="review_alert">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Review" data-tip="Review" onclick="return confirm('Mark as reviewed?')">✅</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="dismiss_alert">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="alert_id" value="<?= (int)$a['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Dismiss" data-tip="Dismiss" onclick="return confirm('Dismiss this alert?')">❌</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="margin-top:var(--space-xl);">
      <div class="empty-state-icon">🛡️</div>
      <h3 class="empty-state-title">No Alerts Found</h3>
      <p class="empty-state-description">No fraud alerts match your filters.</p>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
