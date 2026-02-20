<?php
require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../config/db.php';

require_login();

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Get available scholarships
$stmt = $pdo->query('SELECT * FROM scholarships WHERE status = "open" ORDER BY created_at DESC');
$scholarships = $stmt->fetchAll();

// Get selected scholarship details
$selected_scholarship = null;
$requirements = [];
$scholarship_id = $_GET['scholarship_id'] ?? 0;

if ($scholarship_id) {
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id AND status = "open"');
    $stmt->execute([':id' => $scholarship_id]);
    $selected_scholarship = $stmt->fetch();
    
    if ($selected_scholarship) {
        $stmt = $pdo->prepare('SELECT requirement FROM eligibility_requirements WHERE scholarship_id = :id');
        $stmt->execute([':id' => $scholarship_id]);
        $requirements = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check if already applied
        $stmt = $pdo->prepare('SELECT id FROM applications WHERE user_id = :uid AND scholarship_id = :sid');
        $stmt->execute([':uid' => $user_id, ':sid' => $scholarship_id]);
        if ($stmt->fetch()) {
            $_SESSION['flash'] = 'You have already applied for this scholarship.';
            header('Location: applications.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Apply for Scholarship | Student</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .form-section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    .requirements-list { background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 15px 0; }
    .requirements-list ul { margin: 10px 0; padding-left: 20px; }
    .scholarship-card { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
    .scholarship-card:hover { background: #f5f5f5; }
    .scholarship-card.selected { border-color: #4CAF50; background: #e8f5e9; }
  </style>
</head>
<body>
  <div class="dashboard-app">
    <aside class="sidebar">
      <div class="profile">
        <div class="avatar"><?= strtoupper(substr(($_SESSION['user']['first_name']??$_SESSION['user']['username']),0,1)) ?></div>
        <div>
          <div class="welcome">Welcome,</div>
          <div class="username"><?= htmlspecialchars($_SESSION['user']['first_name'] ?? $_SESSION['user']['username']) ?></div>
        </div>
      </div>
      <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="applications.php">Your Applications</a>
        <a href="apply_scholarship.php">Apply for Scholarship</a>
        <a href="notifications.php">Notifications</a>
        <a href="../auth/logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <div class="header-row">
        <div>
          <h2>Apply for Scholarship</h2>
          <p class="muted">Select a scholarship and complete your application</p>
        </div>
      </div>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="flash success-flash"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="flash error-flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
      <?php endif; ?>

      <section class="panel">
        <h3>Available Scholarships</h3>
        <?php if (empty($scholarships)): ?>
          <p>No open scholarships available at this time.</p>
        <?php else: ?>
          <?php foreach ($scholarships as $sch): ?>
            <div class="scholarship-card <?= $selected_scholarship && $selected_scholarship['id'] == $sch['id'] ? 'selected' : '' ?>" 
                 onclick="window.location.href='?scholarship_id=<?= $sch['id'] ?>'">
              <h4><?= htmlspecialchars($sch['title']) ?></h4>
              <p><strong>Organization:</strong> <?= htmlspecialchars($sch['organization'] ?? 'N/A') ?></p>
              <p><?= htmlspecialchars(substr($sch['description'] ?? '', 0, 150)) ?>...</p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

      <?php if ($selected_scholarship): ?>
        <section class="form-section">
          <h3>Application Form: <?= htmlspecialchars($selected_scholarship['title']) ?></h3>
          
          <?php if (!empty($requirements)): ?>
            <div class="requirements-list">
              <strong>Eligibility Requirements:</strong>
              <ul>
                <?php foreach ($requirements as $req): ?>
                  <li><?= htmlspecialchars($req) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="POST" action="../controllers/ApplicationController.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="scholarship_id" value="<?= $selected_scholarship['id'] ?>">

            <div class="form-group">
              <label>Application Title</label>
              <input type="text" name="title" value="<?= htmlspecialchars($selected_scholarship['title'] . ' Application') ?>" required>
            </div>

            <div class="form-group">
              <label>GPA *</label>
              <input type="number" name="gpa" step="0.01" min="0" max="4.0" placeholder="e.g., 3.5" required>
            </div>

            <div class="form-group">
              <label>
                <input type="checkbox" name="full_time" value="1"> Enrolled Full-time
              </label>
            </div>

            <div class="form-group">
              <label>Additional Information</label>
              <textarea name="other_info" rows="3" placeholder="Any additional information about your eligibility..."></textarea>
            </div>

            <div class="form-group">
              <label>Application Details *</label>
              <textarea name="details" rows="5" placeholder="Tell us why you deserve this scholarship..." required></textarea>
            </div>

            <div class="form-group">
              <label>Supporting Documents (Optional)</label>
              <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <small>Upload transcripts, certificates, or other supporting documents</small>
            </div>

            <button type="submit" class="submit-btn">Submit Application</button>
            <a href="apply_scholarship.php" style="margin-left:10px">Cancel</a>
          </form>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
