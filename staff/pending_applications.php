<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_role(['staff','admin']);
$pdo = getPDO();

// Fetch pending/submitted/under_review applications
$stmt = $pdo->query("SELECT a.id, a.title, a.status, a.created_at, u.username, u.first_name, u.last_name, s.title as scholarship_title
                     FROM applications a
                     LEFT JOIN users u ON a.user_id = u.id
                     LEFT JOIN scholarships s ON a.scholarship_id = s.id
                     WHERE a.status IN ('submitted','pending','under_review')
                     ORDER BY a.created_at ASC");
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Pending Applications | Staff</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div style="max-width:1100px;margin:30px auto">
    <h2>Pending Applications</h2>
    <p class="muted">Select applications to change status in bulk.</p>
    <?php if (!empty($_SESSION['success'])): ?><div class="message success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): ?><div class="message error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>

    <?php if (empty($apps)): ?>
      <p>No pending applications.</p>
    <?php else: ?>
      <form method="POST" action="applications.php">
        <input type="hidden" name="action" value="bulk_change_status">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr><th><input type="checkbox" id="select_all" onclick="toggleAll(this)"></th><th>#</th><th>Applicant</th><th>Title</th><th>Scholarship</th><th>Status</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php foreach ($apps as $a): ?>
              <tr style="border-bottom:1px solid #eee">
                <td style="padding:8px"><input type="checkbox" name="application_ids[]" value="<?= (int)$a['id'] ?>"></td>
                <td style="padding:8px"><?= htmlspecialchars($a['id']) ?></td>
                <td style="padding:8px"><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '') ?: ($a['username'] ?? '')) ?></td>
                <td style="padding:8px"><?= htmlspecialchars($a['title'] ?? 'Application') ?></td>
                <td style="padding:8px"><?= htmlspecialchars($a['scholarship_title'] ?? '—') ?></td>
                <td style="padding:8px"><?= htmlspecialchars($a['status']) ?></td>
                <td style="padding:8px"><?= htmlspecialchars($a['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
          <select name="new_status">
            <option value="under_review">Mark as Under Review</option>
            <option value="approved">Mark as Approved</option>
            <option value="rejected">Mark as Rejected</option>
            <option value="pending">Mark as Pending</option>
          </select>
          <input type="text" name="notes" placeholder="Optional notes for applicants" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px">
          <button class="btn" type="submit">Apply to selected</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
  <script>
    function toggleAll(cb){
      document.querySelectorAll('input[name="application_ids[]"]').forEach(function(i){ i.checked = cb.checked; });
    }
  </script>
</body>
</html>
