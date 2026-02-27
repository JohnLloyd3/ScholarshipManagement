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

            <!-- I. Personal Information -->
            <div class="form-section">
              <h4>I. Personal Information</h4>
              <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required></div>
              <div class="form-group"><label>Sex *</label><select name="sex" required><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option><option value="Prefer not to say">Prefer not to say</option></select></div>
              <div class="form-group"><label>Date of Birth *</label><input type="date" name="dob" required></div>
              <div class="form-group"><label>Age *</label><input type="number" name="age" min="0" required></div>
              <div class="form-group"><label>Civil Status *</label><input type="text" name="civil_status" required placeholder="e.g. Single"></div>
              <div class="form-group"><label>Nationality *</label><input type="text" name="nationality" value="Filipino" required></div>
              <div class="form-group"><label>Mobile Number *</label><input type="text" name="mobile" required></div>
              <div class="form-group"><label>Email Address *</label><input type="email" name="email" required></div>
              <div class="form-group"><label>Complete Home Address *</label><textarea name="home_address" rows="2" required></textarea></div>
            </div>

            <!-- II. Senior High School Information -->
            <div class="form-section">
              <h4>II. Senior High School Information</h4>
              <div class="form-group"><label>Name of Senior High School *</label><input type="text" name="shs_name" required></div>
              <div class="form-group"><label>School Address *</label><input type="text" name="shs_address" required></div>
              <div class="form-group"><label>Strand Taken *</label><input type="text" name="strand" required placeholder="STEM, ABM, HUMSS, TVL, GAS, etc."></div>
              <div class="form-group"><label>General Weighted Average (GWA) *</label><input type="text" name="gwa" required></div>
              <div class="form-group"><label>Year Graduated *</label><input type="text" name="year_graduated" required></div>
            </div>

            <!-- III. College Enrollment Information -->
            <div class="form-section">
              <h4>III. College Enrollment Information</h4>
              <div class="form-group"><label>Intended College/University *</label><input type="text" name="intended_college" required></div>
              <div class="form-group"><label>Course/Degree Program *</label><input type="text" name="course_program" required></div>
              <div class="form-group"><label>Type of Institution *</label><select name="institution_type" required><option value="">Select</option><option value="Public">Public</option><option value="Private">Private</option></select></div>
              <div class="form-group"><label>Admission Letter? *</label><select name="admission_letter" required><option value="">Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
              <div class="form-group"><label>Expected Enrollment Date *</label><input type="date" name="enrollment_date" required></div>
            </div>

            <!-- IV. Family Background -->
            <div class="form-section">
              <h4>IV. Family Background</h4>
              <div class="form-group"><label>Father’s Name</label><input type="text" name="father_name"></div>
              <div class="form-group"><label>Occupation</label><input type="text" name="father_occupation"></div>
              <div class="form-group"><label>Monthly Income</label><input type="text" name="father_income"></div>
              <div class="form-group"><label>Mother’s Name</label><input type="text" name="mother_name"></div>
              <div class="form-group"><label>Occupation</label><input type="text" name="mother_occupation"></div>
              <div class="form-group"><label>Monthly Income</label><input type="text" name="mother_income"></div>
              <div class="form-group"><label>Guardian (if applicable)</label><input type="text" name="guardian"></div>
              <div class="form-group"><label>Total Monthly Family Income</label><input type="text" name="total_income"></div>
              <div class="form-group"><label>Number of Family Members</label><input type="number" name="family_members" min="1"></div>
            </div>

            <!-- V. Scholarship Details -->
            <div class="form-section">
              <h4>V. Scholarship Details</h4>
              <div class="form-group"><label>Scholarship Applying For</label><input type="text" name="scholarship_title" value="<?= htmlspecialchars($selected_scholarship['title']) ?>" readonly></div>
              <div class="form-group"><label>Receiving another scholarship?</label><select name="receiving_other"><option value="No">No</option><option value="Yes">Yes</option></select></div>
              <div class="form-group"><label>If yes, specify</label><input type="text" name="other_scholarship_details"></div>
            </div>

            <!-- VI. Required Documents -->
            <div class="form-section">
              <h4>VI. Required Documents (please attach)</h4>
              <div class="form-group">
                <label><input type="checkbox" name="docs_checklist[]" value="Grade 12 Report Card"> Grade 12 Report Card (Form 138)</label><br>
                <label><input type="checkbox" name="docs_checklist[]" value="Certificate of Graduation"> Certificate of Graduation</label><br>
                <label><input type="checkbox" name="docs_checklist[]" value="Admission Letter"> Admission Letter / Certificate of Enrollment</label><br>
                <label><input type="checkbox" name="docs_checklist[]" value="Proof of Income"> Proof of Income (ITR / Certificate of Indigency)</label><br>
                <label><input type="checkbox" name="docs_checklist[]" value="Valid ID"> Valid ID</label><br>
                <label><input type="checkbox" name="docs_checklist[]" value="2x2 ID Picture"> 2x2 ID Picture</label>
              </div>
              <div class="form-group">
                <label>Upload document files (you may select multiple)</label>
                <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              </div>
            </div>

            <!-- VII. Applicant’s Declaration -->
            <div class="form-section">
              <h4>VII. Applicant’s Declaration</h4>
              <p>I certify that the information provided is true and correct. I understand that providing false information may result in disqualification from the scholarship program.</p>
              <div class="form-group"><label>Applicant’s Name & Signature *</label><input type="text" name="applicant_signature" required></div>
              <div class="form-group"><label>Date *</label><input type="date" name="applicant_date" required></div>
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
