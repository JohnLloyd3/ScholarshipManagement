<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_role(['staff','admin']);
$pdo = getPDO();

// Fetch pending documents
$stmt = $pdo->query("SELECT d.id, d.file_name, d.file_path, d.document_type, d.verification_status, d.uploaded_at, u.username, a.id as application_id, s.title as scholarship_title
                     FROM documents d
                     LEFT JOIN users u ON d.user_id = u.id
                     LEFT JOIN applications a ON d.application_id = a.id
                     LEFT JOIN scholarships s ON a.scholarship_id = s.id
                     WHERE d.verification_status = 'pending'
                     ORDER BY d.uploaded_at ASC");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Documents Queue | Staff</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
  <div style="max-width:1100px;margin:30px auto">
    <h2>Pending Documents</h2>
    <p class="muted">Select documents to verify or reject in bulk.</p>
    <?php if (!empty($_SESSION['success'])): ?><div class="message success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): ?><div class="message error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>

    <?php if (empty($docs)): ?>
      <p>No pending documents.</p>
    <?php else: ?>
      <form method="POST" action="applications.php">
        <input type="hidden" name="action" value="verify_documents_bulk">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr><th><input type="checkbox" id="select_all" onclick="toggleAll(this)"></th><th>File</th><th>Applicant</th><th>Scholarship</th><th>Uploaded</th></tr></thead>
          <tbody>
            <?php foreach ($docs as $d): ?>
              <tr style="border-bottom:1px solid #eee">
                <td style="padding:8px"><input type="checkbox" name="document_ids[]" value="<?= (int)$d['id'] ?>"></td>
                <td style="padding:8px"><a href="../<?= htmlspecialchars($d['file_path']) ?>" target="_blank"><?= htmlspecialchars($d['file_name']) ?></a><br><small><?= htmlspecialchars($d['document_type']) ?></small></td>
                <td style="padding:8px"><?= htmlspecialchars($d['username']) ?></td>
                <td style="padding:8px"><?= htmlspecialchars($d['scholarship_title'] ?? '—') ?></td>
                <td style="padding:8px"><?= htmlspecialchars($d['uploaded_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
          <select name="new_status">
            <option value="verified">Mark as Verified</option>
            <option value="rejected">Mark as Rejected</option>
            <option value="needs_resubmission">Mark as Needs Resubmission</option>
          </select>
          <input type="text" name="notes" placeholder="Optional note for applicants" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:6px">
          <button class="btn" type="submit">Apply to selected</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
  <script>
    function toggleAll(cb){
      document.querySelectorAll('input[name="document_ids[]"]').forEach(function(i){ i.checked = cb.checked; });
    }
  </script>
</body>
</html>
