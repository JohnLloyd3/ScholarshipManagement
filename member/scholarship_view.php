<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . APP_BASE . '/member/scholarships.php');
    exit;
}
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $s = false;
}
if (!$s) {
    header('Location: ' . APP_BASE . '/member/scholarships.php');
    exit;
}
?>
<?php
$page_title = htmlspecialchars($s['title']) . ' - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🎓 <?= htmlspecialchars($s['title']) ?></h1>
  <p class="text-muted"><?= htmlspecialchars($s['organization']) ?></p>
</div>

<div class="content-card">
  <div class="flex justify-between items-start" style="margin-bottom: var(--space-xl);">
    <div>
      <div class="stat-value" style="font-size: 2rem; margin-bottom: 0.5rem;">
        <?= $s['amount'] ? '₱'.number_format($s['amount'], 0) : 'Amount TBA' ?>
      </div>
      <div class="flex gap-2" style="margin-top: var(--space-md);">
        <span class="badge badge-success">Open</span>
        <?php if (!empty($s['deadline'])): ?>
          <span class="badge badge-info">Deadline: <?= date('M d, Y', strtotime($s['deadline'])) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="flex gap-2">
      <a href="apply_scholarship.php?scholarship_id=<?= $s['id'] ?>" class="btn btn-primary">Apply Now</a>
      <a href="scholarships.php" class="btn btn-secondary">← Back</a>
    </div>
  </div>

  <hr style="margin: var(--space-xl) 0; border: none; border-top: 1px solid var(--gray-200);">

  <h3 style="margin-bottom: var(--space-lg);">Description</h3>
  <p style="white-space: pre-wrap; line-height: 1.8; color: var(--gray-700);">
    <?= nl2br(htmlspecialchars($s['description'])) ?>
  </p>

  <?php if (!empty($s['eligibility_requirements'])): ?>
    <hr style="margin: var(--space-xl) 0; border: none; border-top: 1px solid var(--gray-200);">
    <h3 style="margin-bottom: var(--space-lg);">Eligibility Requirements</h3>
    <div style="background: var(--gray-50); padding: var(--space-lg); border-radius: var(--radius-lg); border-left: 4px solid var(--red-primary);">
      <p style="white-space: pre-wrap; line-height: 1.8; color: var(--gray-700); margin: 0;">
        <?= nl2br(htmlspecialchars($s['eligibility_requirements'])) ?>
      </p>
    </div>
  <?php endif; ?>

  <div style="margin-top: var(--space-2xl); display: flex; gap: var(--space-md);">
    <a href="apply_scholarship.php?scholarship_id=<?= $s['id'] ?>" class="btn btn-primary">📝 Apply for this Scholarship</a>
    <a href="scholarships.php" class="btn btn-ghost">View All Scholarships</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
