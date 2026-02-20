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
        <a href="apply_scholarship.php">Scholarships</a>
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
            <div class="form-section">
              <h4>Personal Information</h4>
              <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required></div>
              <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required></div>
              <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name"></div>
              <div class="form-group"><label>Age *</label><input type="number" name="age" min="1" required></div>
              <div class="form-group"><label>Sex *</label><select name="sex" required><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
              <div class="form-group"><label>Status *</label><input type="text" name="status" required></div>
              <div class="form-group"><label>Religion</label><input type="text" name="religion"></div>
              <div class="form-group"><label>Citizenship *</label><input type="text" name="citizenship" required></div>
              <div class="form-group"><label>Date of Birth *</label><input type="date" name="dob" required></div>
              <div class="form-group"><label>Place of Birth *</label><input type="text" name="birth_place" required></div>
              <div class="form-group"><label>Complete Mailing Address *</label><input type="text" name="mailing_address" required></div>
              <div class="form-group"><label>Home/Provincial Address</label><input type="text" name="provincial_address"></div>
              <div class="form-group"><label>Tel./Mobile Number *</label><input type="text" name="contact_number" required></div>
            </div>
            <div class="form-section">
              <h4>Family Background</h4>
              <div class="form-group"><label>Father's Name</label><input type="text" name="father_name"></div>
              <div class="form-group"><label>Father's Status</label><select name="father_status"><option value="Living">Living</option><option value="Deceased">Deceased</option></select></div>
              <div class="form-group"><label>Father's Occupation</label><input type="text" name="father_occupation"></div>
              <div class="form-group"><label>Mother's Name</label><input type="text" name="mother_name"></div>
              <div class="form-group"><label>Mother's Status</label><select name="mother_status"><option value="Living">Living</option><option value="Deceased">Deceased</option></select></div>
              <div class="form-group"><label>Mother's Occupation</label><input type="text" name="mother_occupation"></div>
              <div class="form-group"><label>Total Parents' Gross Income</label><input type="number" name="gross_income" min="0"></div>
              <div class="form-group"><label>Brothers/Sisters Enjoying Scholarship</label><textarea name="siblings_scholarship" rows="2"></textarea></div>
              <div class="form-group"><label>Number of children in the family</label><input type="number" name="children_count" min="1"></div>
            </div>
            <div class="form-section">
              <h4>Academic Information</h4>
              <div class="form-group"><label>School Name (High School) *</label><input type="text" name="school_name" required></div>
              <div class="form-group"><label>School Address *</label><input type="text" name="school_address" required></div>
              <div class="form-group"><label>School Type *</label><select name="school_type" required><option value="">Select</option><option value="Public">Public</option><option value="Private">Private</option><option value="Vocational">Vocational</option></select></div>
              <div class="form-group"><label>Highest Grade/Year *</label><input type="text" name="highest_grade" required></div>
              <div class="form-group"><label>Date of Graduation *</label><input type="date" name="graduation_date" required></div>
              <div class="form-group"><label>Report Card Average *</label><input type="text" name="report_card_average" required></div>
              <div class="form-group"><label>Rank in Graduating Class</label><input type="text" name="class_rank"></div>
              <div class="form-group"><label>Academic Awards/Honors Received</label><textarea name="awards" rows="2"></textarea></div>
            </div>
            <div class="form-section">
              <h4>Scholarship Choices</h4>
              <div class="form-group"><label>First Choice Degree Program</label><input type="text" name="first_choice"></div>
              <div class="form-group"><label>Second Choice Degree Program</label><input type="text" name="second_choice"></div>
              <div class="form-group"><label>Third Choice Degree Program</label><input type="text" name="third_choice"></div>
              <div class="form-group"><label>School Intended to Enroll In</label><input type="text" name="intended_school"></div>
              <div class="form-group"><label>Factors that Motivated You</label><textarea name="motivation" rows="2"></textarea></div>
            </div>
            <div class="form-section">
              <h4>Parent/Legal Guardian Declaration</h4>
              <div class="form-group">
                <textarea name="parent_declaration" rows="4" readonly>I/We hereby certify to the truthfulness and completeness of information provided. Any misinformation will automatically disqualify my/our child from the Scholarship Program. I/We are also willing to refund all financial benefits received plus the interest if such misinformation is discovered after my/our child accepted the reward. In connection with this application for financial aide, I/we hereby authorize the Scholarship Committee to conduct a background check on the family finances and to visit our family dwelling.</textarea>
              </div>
              <div class="form-group"><label>Parent/Guardian Name *</label><input type="text" name="guardian_name" required></div>
              <div class="form-group"><label>Signature *</label><input type="text" name="guardian_signature" required></div>
              <div class="form-group"><label>Date *</label><input type="date" name="guardian_date" required></div>
            </div>
            <div class="form-section">
              <h4>Supporting Documents</h4>
              <div class="form-group">
                <label>Upload Documents (transcripts, certificates, etc.)</label>
                <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              </div>
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
