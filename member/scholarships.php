<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/helpers.php';

$q = trim($_GET['q'] ?? '');
try {
    $pdo = getPDO();
    if ($q !== '') {
        $stmt = $pdo->prepare("SELECT id, title, organization, amount, deadline, status, description FROM scholarships WHERE status = 'open' AND (title LIKE :q OR organization LIKE :q) ORDER BY deadline IS NULL, deadline ASC");
        $stmt->execute([':q' => "%{$q}%"]);
    } else {
        $stmt = $pdo->query("SELECT id, title, organization, amount, deadline, status, description FROM scholarships WHERE status = 'open' ORDER BY deadline IS NULL, deadline ASC");
    }
    $scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $scholarships = [];
}
?>
<?php
$page_title = 'Browse Scholarships - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1 style="margin-bottom: 0.5rem;">🎓 Available Scholarships</h1>
  <p class="text-muted" style="margin: 0;">Browse and apply for scholarships that match your goals</p>
</div>

<div class="content-card" style="margin-bottom: var(--space-xl);">
  <form method="get" class="flex gap-2">
    <input 
      name="q" 
      value="<?= htmlspecialchars($q) ?>" 
      placeholder="Search by title or organization" 
      class="form-input" 
      style="flex: 1;"
    >
    <button class="btn btn-primary" type="submit">🔍 Search</button>
  </form>
</div>

<?php if (empty($scholarships)): ?>
  <div class="content-card text-center" style="padding: var(--space-3xl);">
    <div style="font-size: 3rem; margin-bottom: var(--space-lg);">📚</div>
    <h3>No Scholarships Found</h3>
    <p class="text-muted">Try adjusting your search or check back later for new opportunities.</p>
  </div>
<?php else: ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: var(--space-xl);">
    <?php foreach ($scholarships as $s): ?>
      <div class="card">
        <div class="card-header">
          <div class="flex justify-between items-center">
            <h3 class="card-title"><?= htmlspecialchars($s['title']) ?></h3>
            <span class="badge badge-success">Open</span>
          </div>
          <p class="card-subtitle"><?= htmlspecialchars($s['organization']) ?></p>
        </div>
        
        <div class="card-body">
          <p><?= nl2br(htmlspecialchars(substr($s['description'], 0, 150))) ?><?php if (strlen($s['description']) > 150) echo '...'; ?></p>
        </div>
        
        <div class="card-footer">
          <div>
            <div class="stat-value" style="font-size: 1.5rem; margin-bottom: 0.25rem;">
              <?= $s['amount'] ? '₱'.number_format($s['amount'], 0) : 'TBA' ?>
            </div>
            <div class="text-muted" style="font-size: 0.875rem;">
              Deadline: <?= $s['deadline'] ? date('M d, Y', strtotime($s['deadline'])) : 'N/A' ?>
            </div>
          </div>
          <div class="flex gap-1">
            <a href="scholarship_view.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            <a href="apply_scholarship.php?scholarship_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Apply</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
