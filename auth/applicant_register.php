<?php
// Applicant Registration Form
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors = ['Invalid request. Please refresh and try again.'];
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $religion = trim($_POST['religion'] ?? '');
    $citizenship = trim($_POST['citizenship'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
    $mailing_address = trim($_POST['mailing_address'] ?? '');
    $provincial_address = trim($_POST['provincial_address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $father_status = trim($_POST['father_status'] ?? '');
    $father_occupation = trim($_POST['father_occupation'] ?? '');
    $mother_name = trim($_POST['mother_name'] ?? '');
    $mother_status = trim($_POST['mother_status'] ?? '');
    $mother_occupation = trim($_POST['mother_occupation'] ?? '');
    $gross_income = trim($_POST['gross_income'] ?? '');
    $siblings_scholarship = trim($_POST['siblings_scholarship'] ?? '');
    $children_count = trim($_POST['children_count'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $school_type = trim($_POST['school_type'] ?? '');
    $highest_grade = trim($_POST['highest_grade'] ?? '');
    $graduation_date = trim($_POST['graduation_date'] ?? '');
    $report_card_average = trim($_POST['report_card_average'] ?? '');
    $class_rank = trim($_POST['class_rank'] ?? '');
    $awards = trim($_POST['awards'] ?? '');
    $first_choice = trim($_POST['first_choice'] ?? '');
    $second_choice = trim($_POST['second_choice'] ?? '');
    $third_choice = trim($_POST['third_choice'] ?? '');
    $intended_school = trim($_POST['intended_school'] ?? '');
    $motivation = trim($_POST['motivation'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_signature = trim($_POST['guardian_signature'] ?? '');
    $guardian_date = trim($_POST['guardian_date'] ?? '');

    // Basic validation
    $errors = $errors ?? [];
    if (!$username || !$email || !$password || !$first_name || !$last_name) {
        $errors[] = 'All fields are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    // Prevent duplicate usernames/emails
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username OR email = :email');
    $stmt->execute(['username' => $username, 'email' => $email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Username or email already exists.';
    }

    if (!$errors) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // Insert into users table
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, first_name, last_name, phone, address, role) VALUES (:username, :email, :password, :first_name, :last_name, :phone, :address, :role)');
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hashed,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $contact_number,
            'address' => $mailing_address,
            'role' => 'student'
        ]);
        $user_id = $pdo->lastInsertId();
        // Insert into student_profiles table (new canonical profile storage)
        $stmt = $pdo->prepare('INSERT INTO student_profiles (user_id, gpa, university, course, enrollment_status) VALUES (:user_id, :gpa, :university, :course, :enrollment_status)');
        $stmt->execute([
            'user_id' => $user_id,
            'gpa' => $report_card_average ?: null,
            'university' => $intended_school ?: null,
            'course' => $first_choice ?: null,
            'enrollment_status' => 'full-time'
        ]);
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<?php
$page_title = 'Applicant Registration - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
?>

<style>
  body {
    background: var(--gray-50);
  }
  
  .registration-container {
    max-width: 900px;
    margin: var(--space-2xl) auto;
    padding: var(--space-xl);
  }
  
  .registration-header {
    text-align: center;
    margin-bottom: var(--space-2xl);
  }
  
  .registration-header h1 {
    font-size: 2rem;
    color: var(--red-primary);
    margin-bottom: var(--space-sm);
  }
  
  .registration-header p {
    color: var(--gray-600);
  }
  
  fieldset {
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
    margin-bottom: var(--space-xl);
    background: var(--white);
  }
  
  legend {
    font-weight: 700;
    color: var(--red-primary);
    padding: 0 var(--space-md);
    font-size: 1.125rem;
  }
  
  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-lg);
  }
  
  @media (max-width: 768px) {
    .form-row {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="registration-container">
  <div class="registration-header">
    <h1>📝 Applicant Registration</h1>
    <p>Complete the form below to create your scholarship account</p>
  </div>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <fieldset>
      <legend>🔐 Account Information</legend>
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input type="text" name="username" class="form-input" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-input" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Password *</label>
        <input type="password" name="password" class="form-input" required minlength="6">
      </div>
    </fieldset>
    
    <fieldset>
      <legend>👤 Personal Information</legend>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input type="text" name="first_name" class="form-input" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input type="text" name="last_name" class="form-input" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Middle Name</label>
        <input type="text" name="middle_name" class="form-input" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Age *</label>
          <input type="number" name="age" class="form-input" min="1" required value="<?= htmlspecialchars($_POST['age'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Sex *</label>
          <select name="sex" class="form-select" required>
            <option value="">Select</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status *</label>
          <input type="text" name="status" class="form-input" required value="<?= htmlspecialchars($_POST['status'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Religion</label>
          <input type="text" name="religion" class="form-input" value="<?= htmlspecialchars($_POST['religion'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Citizenship *</label>
          <input type="text" name="citizenship" class="form-input" required value="<?= htmlspecialchars($_POST['citizenship'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Date of Birth *</label>
          <input type="date" name="dob" class="form-input" required value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Place of Birth *</label>
        <input type="text" name="birth_place" class="form-input" required value="<?= htmlspecialchars($_POST['birth_place'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Complete Mailing Address *</label>
        <input type="text" name="mailing_address" class="form-input" required value="<?= htmlspecialchars($_POST['mailing_address'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Home/Provincial Address</label>
        <input type="text" name="provincial_address" class="form-input" value="<?= htmlspecialchars($_POST['provincial_address'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Tel./Mobile Number *</label>
        <input type="text" name="contact_number" class="form-input" required value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
      </div>
    </fieldset>
    <fieldset>
      <legend>👨‍👩‍👧‍👦 Family Background</legend>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Father's Name</label>
          <input type="text" name="father_name" class="form-input" value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Father's Status</label>
          <select name="father_status" class="form-select">
            <option value="Living">Living</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Father's Occupation</label>
        <input type="text" name="father_occupation" class="form-input" value="<?= htmlspecialchars($_POST['father_occupation'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Mother's Name</label>
          <input type="text" name="mother_name" class="form-input" value="<?= htmlspecialchars($_POST['mother_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Mother's Status</label>
          <select name="mother_status" class="form-select">
            <option value="Living">Living</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Mother's Occupation</label>
        <input type="text" name="mother_occupation" class="form-input" value="<?= htmlspecialchars($_POST['mother_occupation'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Total Parents' Gross Income</label>
          <input type="number" name="gross_income" class="form-input" min="0" value="<?= htmlspecialchars($_POST['gross_income'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Number of Children in Family</label>
          <input type="number" name="children_count" class="form-input" min="1" value="<?= htmlspecialchars($_POST['children_count'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Brothers/Sisters Enjoying Scholarship</label>
        <textarea name="siblings_scholarship" class="form-input" rows="2"><?= htmlspecialchars($_POST['siblings_scholarship'] ?? '') ?></textarea>
      </div>
    </fieldset>
    <fieldset>
      <legend>🎓 Academic Information</legend>
      <div class="form-group">
        <label class="form-label">School Name (High School) *</label>
        <input type="text" name="school_name" class="form-input" required value="<?= htmlspecialchars($_POST['school_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">School Address *</label>
        <input type="text" name="school_address" class="form-input" required value="<?= htmlspecialchars($_POST['school_address'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">School Type *</label>
          <select name="school_type" class="form-select" required>
            <option value="">Select</option>
            <option value="Public">Public</option>
            <option value="Private">Private</option>
            <option value="Vocational">Vocational</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Highest Grade/Year *</label>
          <input type="text" name="highest_grade" class="form-input" required value="<?= htmlspecialchars($_POST['highest_grade'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date of Graduation *</label>
          <input type="date" name="graduation_date" class="form-input" required value="<?= htmlspecialchars($_POST['graduation_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Report Card Average *</label>
          <input type="text" name="report_card_average" class="form-input" required value="<?= htmlspecialchars($_POST['report_card_average'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Rank in Graduating Class</label>
        <input type="text" name="class_rank" class="form-input" value="<?= htmlspecialchars($_POST['class_rank'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Academic Awards/Honors Received</label>
        <textarea name="awards" class="form-input" rows="2"><?= htmlspecialchars($_POST['awards'] ?? '') ?></textarea>
      </div>
    </fieldset>
    <fieldset>
      <legend>✍️ Parent/Legal Guardian Declaration</legend>
      <div class="form-group">
        <textarea name="parent_declaration" class="form-input" rows="6" readonly style="background: var(--gray-50);">I/We hereby certify to the truthfulness and completeness of information provided. Any misinformation will automatically disqualify my/our child from the Scholarship Program. I/We are also willing to refund all financial benefits received plus the interest if such misinformation is discovered after my/our child accepted the reward. In connection with this application for financial aide, I/we hereby authorize the Scholarship Committee to conduct a background check on the family finances and to visit our family dwelling.</textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Parent/Guardian Name *</label>
        <input type="text" name="guardian_name" class="form-input" required value="<?= htmlspecialchars($_POST['guardian_name'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Signature *</label>
          <input type="text" name="guardian_signature" class="form-input" required value="<?= htmlspecialchars($_POST['guardian_signature'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Date *</label>
          <input type="date" name="guardian_date" class="form-input" required value="<?= htmlspecialchars($_POST['guardian_date'] ?? '') ?>">
        </div>
      </div>
    </fieldset>
    
    <div style="display: flex; gap: var(--space-md); justify-content: flex-end; margin-top: var(--space-xl);">
      <a href="../" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Register</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
