<?php
require_once __DIR__ . '/../auth/helpers.php';
require_role(['staff','admin']);
require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();
$q = trim($_GET['q'] ?? '');

function likeParam($s){ return '%'.str_replace('%','\%',$s).'%'; }

$users = $scholarships = $applications = [];
if ($q !== '') {
    $p = likeParam($q);
    $ustmt = $pdo->prepare("SELECT id, first_name, last_name, email, username FROM users WHERE first_name LIKE :q OR last_name LIKE :q OR email LIKE :q OR username LIKE :q LIMIT 50");
    $ustmt->execute([':q'=>$p]);
    $users = $ustmt->fetchAll(PDO::FETCH_ASSOC);

    $sstmt = $pdo->prepare("SELECT id, title, organization, status FROM scholarships WHERE title LIKE :q OR organization LIKE :q OR description LIKE :q LIMIT 50");
    $sstmt->execute([':q'=>$p]);
    $scholarships = $sstmt->fetchAll(PDO::FETCH_ASSOC);

    $astmt = $pdo->prepare("SELECT a.id, a.status, u.first_name, u.last_name, u.email, s.title AS scholarship_title FROM applications a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN scholarships s ON a.scholarship_id = s.id WHERE s.title LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.email LIKE :q LIMIT 100");
    $astmt->execute([':q'=>$p]);
    $applications = $astmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<?php
$page_title = 'Global Search - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>🔍 Global Search</h1>
  <p class="text-muted">Search across users, scholarships, and applications</p>
</div>

<div class="content-card">
  <form method="get" style="margin-bottom:var(--space-xl);display:flex;gap:var(--space-md)">
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search users, scholarships, applications" class="form-input" style="flex:1">
    <button class="btn btn-primary">🔍 Search</button>
  </form>

  <?php if ($q === ''): ?>
    <div class="empty-state">
      <div class="empty-state-icon">🔍</div>
      <h3 class="empty-state-title">Start Searching</h3>
      <p class="empty-state-description">Enter a search term to find users, scholarships, or applications.</p>
    </div>
  <?php else: ?>
    <div style="display:grid;gap:var(--space-xl)">
      <div>
        <h3 style="margin-bottom:var(--space-lg)">Users (<?= count($users) ?>)</h3>
        <?php if ($users): ?>
          <ul style="list-style:none;padding:0;margin:0">
            <?php foreach($users as $u): ?>
              <li style="padding:var(--space-md);border-bottom:1px solid var(--gray-200)">
                <a href="../admin/users.php?view=<?= (int)$u['id'] ?>" class="text-primary"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></a>
                <span class="text-muted"> — <?= htmlspecialchars($u['email']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No users found.</p>
        <?php endif; ?>
      </div>

      <div>
        <h3 style="margin-bottom:var(--space-lg)">Scholarships (<?= count($scholarships) ?>)</h3>
        <?php if ($scholarships): ?>
          <ul style="list-style:none;padding:0;margin:0">
            <?php foreach($scholarships as $s): ?>
              <li style="padding:var(--space-md);border-bottom:1px solid var(--gray-200)">
                <a href="../member/scholarship_view.php?id=<?= (int)$s['id'] ?>" class="text-primary"><?= htmlspecialchars($s['title']) ?></a>
                <span class="text-muted"> — <?= htmlspecialchars($s['organization']) ?></span>
                <span class="status-badge status-<?= strtolower($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No scholarships found.</p>
        <?php endif; ?>
      </div>

      <div>
        <h3 style="margin-bottom:var(--space-lg)">Applications (<?= count($applications) ?>)</h3>
        <?php if ($applications): ?>
          <ul style="list-style:none;padding:0;margin:0">
            <?php foreach($applications as $a): ?>
              <li style="padding:var(--space-md);border-bottom:1px solid var(--gray-200)">
                <a href="application_view.php?id=<?= (int)$a['id'] ?>" class="text-primary">Application #<?= (int)$a['id'] ?></a>
                <span class="text-muted"> — <?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?> — <?= htmlspecialchars($a['scholarship_title']) ?></span>
                <span class="status-badge status-<?= strtolower($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No applications found.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
