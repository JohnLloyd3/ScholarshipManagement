<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

$q = trim($_GET['q'] ?? '');
try {
    $pdo = getPDO();
    $user_id = $_SESSION['user_id'];
    
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT id, title, organization, amount, deadline, status, description FROM scholarships WHERE status = 'open' AND (title LIKE :q OR organization LIKE :q) ORDER BY deadline IS NULL, deadline ASC");
        $stmt->execute([':q' => "%{$q}%"]);
    } else {
        $stmt = $pdo->query("SELECT id, title, organization, amount, deadline, status, description FROM scholarships WHERE status = 'open' ORDER BY deadline IS NULL, deadline ASC");
    }
    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all scholarship IDs that the user has already applied for (excluding drafts)
    $appliedStmt = $pdo->prepare("SELECT scholarship_id, status FROM applications WHERE user_id = :uid AND status != 'draft'");
    $appliedStmt->execute([':uid' => $user_id]);
    $appliedScholarships = [];
    while ($row = $appliedStmt->fetch(PDO::FETCH_ASSOC)) {
        $appliedScholarships[$row['scholarship_id']] = $row['status'];
    }
} catch (Exception $e) {
    $scholarships = [];
    $appliedScholarships = [];
}
?>
<?php
$page_title = 'Browse Scholarships - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1 style="margin:0;">🎓 Available Scholarships</h1>
</div>

<style>
.scholarship-card { transition: all 0.3s ease; }
.scholarship-card.minimized { max-height: 80px; overflow: hidden; }
.scholarship-card.minimized .card-details { display: none; }
.scholarship-card.minimized .card-actions { display: none; }
.scholarship-card .card-header { cursor: pointer; user-select: none; }
.scholarship-card .card-header:hover { background: #f5f5f5; border-radius: 8px; padding: 0.25rem; margin: -0.25rem; }
</style>

<div class="content-card" style="margin-bottom:1.5rem;">
  <form method="get" style="display:flex;gap:0.75rem;">
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by Scholarship" class="form-input" style="flex:1;">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
</div>

<?php if (empty($scholarships)): ?>
  <div class="content-card" style="text-align:center;padding:3rem;">
    <div style="font-size:2.5rem;margin-bottom:1rem;opacity:0.4;"><i class="fas fa-graduation-cap"></i></div>
    <h3>No Scholarships Found</h3>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;">
    <?php foreach ($scholarships as $s): ?>
      <?php
        // Get average rating for this scholarship
        $avgRating = 0; $ratingCount = 0;
        try {
          $ratingStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count FROM feedback WHERE scholarship_id = :sid");
          $ratingStmt->execute([':sid' => $s['id']]);
          $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
          $avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
          $ratingCount = $ratingData['rating_count'] ?? 0;
        } catch (Exception $e) { /* feedback table schema differs */ }
      ?>
      <div class="content-card scholarship-card" style="display:flex;flex-direction:column;justify-content:space-between;gap:1rem;padding:1.25rem;">
        <div class="card-header" onclick="toggleCard(this)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">
            <h3 style="font-weight:700;font-size:0.9375rem;color:#1a1a2e;margin:0;"><?= htmlspecialchars($s['title']) ?></h3>
            <div style="display:flex;gap:0.5rem;align-items:center;">
              <span style="background:#E8F5E9;color:#2E7D32;padding:2px 8px;border-radius:999px;font-size:0.7rem;font-weight:600;flex-shrink:0;">Open</span>
              <i class="fas fa-chevron-down" style="font-size:0.75rem;color:#9E9E9E;transition:transform 0.3s;"></i>
            </div>
          </div>
          <div style="font-size:0.8rem;color:#E53935;font-weight:500;"><?= htmlspecialchars($s['organization'] ?? '') ?></div>
        </div>
        <div class="card-details">
          <?php if (!empty($s['description'])): ?>
            <div style="font-size:0.8125rem;color:#757575;line-height:1.5;margin-bottom:0.5rem;"><?= htmlspecialchars(substr($s['description'], 0, 120)) ?><?= strlen($s['description']) > 120 ? '...' : '' ?></div>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:0.35rem;margin-top:0.5rem;">
            <div style="color:#FFA000;font-size:0.9rem;letter-spacing:1px;">
              <?php
                $fullStars = floor($avgRating);
                $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
                for ($i = 0; $i < $fullStars; $i++) echo '★';
                if ($hasHalfStar) echo '⯨';
                for ($i = 0; $i < (5 - $fullStars - ($hasHalfStar ? 1 : 0)); $i++) echo '☆';
              ?>
            </div>
            <span style="font-size:0.8rem;color:#1a1a2e;font-weight:700;"><?= $avgRating > 0 ? number_format($avgRating, 1) : '0.0' ?></span>
            <span style="font-size:0.75rem;color:#9E9E9E;">(<?= $ratingCount ?>)</span>
          </div>
        </div>
        <div class="card-actions" style="border-top:1px solid #D1D5DB;padding-top:0.875rem;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:1.125rem;font-weight:800;color:#1a1a2e;"><?= $s['amount'] ? '&#8369;'.number_format($s['amount'], 0) : 'TBA' ?></div>
            <div style="font-size:0.75rem;color:#9E9E9E;">Deadline: <?= $s['deadline'] ? date('M d, Y', strtotime($s['deadline'])) : 'N/A' ?></div>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <a href="scholarship_view.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            <?php if (isset($appliedScholarships[$s['id']])): ?>
              <button class="btn btn-secondary btn-sm" disabled style="cursor: not-allowed; opacity: 0.6;">
                <i class="fas fa-check"></i> Applied
              </button>
            <?php else: ?>
              <a href="apply_scholarship.php?scholarship_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Apply</a>
            <?php endif; ?>
          </div>
        </div>
        <?php if (isset($appliedScholarships[$s['id']])): ?>
          <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 0.75rem; border-radius: 6px; margin-top: -0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
              <span style="font-size: 0.8125rem; color: #92400e; font-weight: 500;">
                You have already applied for this scholarship. 
                <span style="text-transform: capitalize; font-weight: 700;">
                  Status: <?= str_replace('_', ' ', $appliedScholarships[$s['id']]) ?>
                </span>
              </span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
function toggleCard(header) {
  const card = header.closest('.scholarship-card');
  const icon = header.querySelector('.fa-chevron-down');
  card.classList.toggle('minimized');
  if (card.classList.contains('minimized')) {
    icon.style.transform = 'rotate(-90deg)';
  } else {
    icon.style.transform = 'rotate(0deg)';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
