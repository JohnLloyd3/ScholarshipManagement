<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();
if (!defined('APP_BASE')) define('APP_BASE', '');
requireLogin();
$pdo = getPDO();
$user = $_SESSION['user'] ?? [];
$user_id = $_SESSION['user_id'] ?? 0;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT d.*, a.user_id as applicant_id FROM documents d LEFT JOIN applications a ON d.application_id = a.id WHERE d.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $doc = false;
}

if (!$doc) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

// Authorization: allow if owner, staff, or admin
$role = $user['role'] ?? 'student';
$owner = (int)($doc['applicant_id'] ?? 0);
if ($role === 'student' && $owner !== (int)$user_id) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$filePath = __DIR__ . '/../' . $doc['file_path'];
if (!is_file($filePath)) {
    http_response_code(404);
    echo 'File missing on server.';
    exit;
}

$webPath = rtrim(APP_BASE, '/') . '/' . ltrim($doc['file_path'], '/');
$mime = $doc['mime_type'] ?? mime_content_type($filePath);

?>
<?php
$page_title = 'Document Viewer - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📄 <?= htmlspecialchars($doc['file_name']) ?></h1>
  <p class="text-muted">Type: <?= htmlspecialchars($mime) ?> • Uploaded: <?= htmlspecialchars($doc['uploaded_at'] ?? '') ?></p>
</div>

<div class="content-card">
  <div style="margin-bottom: var(--space-xl);">
    <?php if (strpos($mime, 'pdf') !== false): ?>
      <div id="pdf-viewer">
        <canvas id="pdf-canvas" style="width:100%;border:1px solid var(--gray-200);background:var(--white);border-radius:var(--r-lg);box-shadow:var(--shadow-md);"></canvas>
        <noscript>
          <iframe src="<?= $webPath ?>" style="width:100%;height:80vh;border:0;border-radius:var(--r-lg);"></iframe>
        </noscript>
      </div>
      <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.10.1/pdf.min.js"></script>
      <script>
        (function(){
          if (!window.pdfjsLib) return;
          pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.10.1/pdf.worker.min.js';
          const url = '<?= $webPath ?>';
          const canvas = document.getElementById('pdf-canvas');
          const ctx = canvas.getContext('2d');
          pdfjsLib.getDocument(url).promise.then(function(pdf) {
            pdf.getPage(1).then(function(page) {
              const viewport = page.getViewport({ scale: 1.2 });
              const containerWidth = Math.min(1000, document.getElementById('pdf-viewer').clientWidth);
              const scale = (containerWidth / viewport.width) * 1.0;
              const scaledViewport = page.getViewport({ scale: viewport.scale * scale });
              canvas.height = scaledViewport.height;
              canvas.width = scaledViewport.width;
              page.render({ canvasContext: ctx, viewport: scaledViewport });
            });
          }).catch(function(err){
            console.error('PDF render error', err);
            var iframe = document.createElement('iframe');
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.height = '80vh';
            iframe.style.border = '0';
            iframe.style.borderRadius = 'var(--r-lg)';
            var v = document.getElementById('pdf-viewer');
            v.innerHTML = '';
            v.appendChild(iframe);
          });
        })();
      </script>
    <?php elseif (strpos($mime, 'image/') === 0): ?>
      <img src="<?= $webPath ?>" alt="<?= htmlspecialchars($doc['file_name']) ?>" style="max-width:100%;height:auto;border:1px solid var(--gray-200);padding:var(--space-lg);background:var(--white);border-radius:var(--r-lg);box-shadow:var(--shadow-md);">
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">📄</div>
        <h3 class="empty-state-title">Preview Not Available</h3>
        <p class="empty-state-description">This file type cannot be previewed. Please download to view.</p>
      </div>
    <?php endif; ?>
  </div>

  <div style="display: flex; gap: var(--space-md);">
    <a href="<?= $webPath ?>" download class="btn btn-primary">⬇️ Download</a>
    <button onclick="window.close()" class="btn btn-secondary">Close</button>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
