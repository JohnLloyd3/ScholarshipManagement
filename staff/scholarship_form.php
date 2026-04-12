<?php
startSecureSession();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
requireLogin();
requireAnyRole(['staff','admin'], 'Staff access required');

$pdo = getPDO();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sch = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id');
    $stmt->execute([':id'=>$id]);
    $sch = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: scholarship_form.php' . ($id ? '?id='.$id : ''));
        exit;
    }
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $org = trim($_POST['organization'] ?? '');
    $elig = trim($_POST['eligibility'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $deadline = trim($_POST['deadline'] ?? null);
    $status = $_POST['status'] ?? 'open';

    if ($id) {
        $upd = $pdo->prepare('UPDATE scholarships SET title=:title, description=:desc, organization=:org, eligibility=:elig, amount=:amount, deadline=:deadline, status=:status, updated_at=NOW() WHERE id = :id');
        $upd->execute([':title'=>$title, ':desc'=>$desc, ':org'=>$org, ':elig'=>$elig, ':amount'=>$amount, ':deadline'=>$deadline, ':status'=>$status, ':id'=>$id]);
        $_SESSION['success'] = 'Scholarship updated.';
    } else {
        $ins = $pdo->prepare('INSERT INTO scholarships (title, description, organization, eligibility, amount, deadline, status, created_at) VALUES (:title, :desc, :org, :elig, :amount, :deadline, :status, NOW())');
        $ins->execute([':title'=>$title, ':desc'=>$desc, ':org'=>$org, ':elig'=>$elig, ':amount'=>$amount, ':deadline'=>$deadline, ':status'=>$status]);
        
        $_SESSION['success'] = 'Scholarship created.';
    }
    header('Location: scholarships_manage.php'); exit;
}

?>
<?php
$page_title = ($sch ? 'Edit' : 'Create') . ' Scholarship - ScholarHub';
$base_path = '../';
$csrf_token = generateCSRFToken();
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><?= $sch ? '✏️ Edit' : '➕ Create' ?> Scholarship</h1>
  <p class="text-muted"><?= $sch ? 'Update scholarship details' : 'Add a new scholarship opportunity' ?></p>
</div>

<div class="content-card">
  <a href="scholarships_manage.php" class="btn btn-secondary" style="margin-bottom:var(--space-xl)">← Back to List</a>
  
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="form-group">
      <label class="form-label">Title *</label>
      <input name="title" required value="<?= htmlspecialchars($sch['title'] ?? '') ?>" class="form-input">
    </div>
    
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-input" rows="6"><?= htmlspecialchars($sch['description'] ?? '') ?></textarea>
    </div>
    
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Organization</label>
        <input name="organization" value="<?= htmlspecialchars($sch['organization'] ?? '') ?>" class="form-input">
      </div>
      
      <div class="form-group">
        <label class="form-label">Amount</label>
        <input name="amount" value="<?= htmlspecialchars($sch['amount'] ?? '') ?>" class="form-input" placeholder="10000">
      </div>
    </div>
    
    <div class="form-group">
      <label class="form-label">Eligibility Requirements</label>
      <input name="eligibility" value="<?= htmlspecialchars($sch['eligibility'] ?? '') ?>" class="form-input">
    </div>
    
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Deadline</label>
        <input type="date" name="deadline" value="<?= htmlspecialchars(isset($sch['deadline']) ? substr($sch['deadline'],0,10) : '') ?>" class="form-input">
      </div>
      
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php $opts=['open','closed','cancelled']; foreach($opts as $o) echo '<option '.(($sch['status'] ?? 'open')==$o?'selected':'').' value="'.$o.'">'.ucfirst($o).'</option>'; ?>
        </select>
      </div>
    </div>
    
    <div style="margin-top:var(--space-xl)">
      <button class="btn btn-primary" type="submit"><?= $sch ? '💾 Save Changes' : '➕ Create Scholarship' ?></button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
