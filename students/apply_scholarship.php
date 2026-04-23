<?php
/**
 * STUDENT - APPLY FOR SCHOLARSHIP
 * Role: Student
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();
requireLogin();
requireRole('student', 'Student access required');

$pdo     = getPDO();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->query('SELECT * FROM scholarships WHERE status = "open" ORDER BY created_at DESC');
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC);

$profile = [];
try {
    $ps = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = :uid');
    $ps->execute([':uid' => $user_id]);
    $profile = $ps->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $profile = []; }

$selected_scholarship = null;
$requirements         = [];
$required_documents   = [];
$days_remaining       = null;
$eligible             = null;
$eligibility_notes    = [];
$scholarship_id       = (int)($_GET['scholarship_id'] ?? 0);

if ($scholarship_id) {
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id AND status = "open"');
    $stmt->execute([':id' => $scholarship_id]);
    $selected_scholarship = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_scholarship) {
        $stmt = $pdo->prepare('SELECT requirement, requirement_type, value FROM eligibility_requirements WHERE scholarship_id = :id');
        $stmt->execute([':id' => $scholarship_id]);
        $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        try {
            $dstmt = $pdo->prepare('SELECT document_name FROM scholarship_documents WHERE scholarship_id = :id');
            $dstmt->execute([':id' => $scholarship_id]);
            $required_documents = $dstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) { $required_documents = []; }

        if (!empty($selected_scholarship['deadline'])) {
            $diff = strtotime($selected_scholarship['deadline']) - time();
            $days_remaining = $diff > 0 ? (int)ceil($diff / 86400) : 0;
        }

        $eligible = true;
        foreach ($requirements as $r) {
            $rtype = $r['requirement_type'] ?? '';
            $val   = $r['value'] ?? '';
            if ($rtype === 'gpa') {
                $min = floatval($val);
                $user_gpa = floatval($profile['gpa'] ?? 0);
                if ($user_gpa < $min) { $eligible = false; $eligibility_notes[] = "Min GPA of $min required (yours: $user_gpa)"; }
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM applications WHERE user_id = :uid AND scholarship_id = :sid');
        $stmt->execute([':uid' => $user_id, ':sid' => $scholarship_id]);
        if ($stmt->fetch()) {
            $_SESSION['flash'] = 'You have already applied for this scholarship.';
            header('Location: applications.php'); exit;
        }
    }
}

// If no scholarship selected, redirect to scholarships page
if (!$selected_scholarship) {
    $_SESSION['flash'] = 'Please select a scholarship to apply for.';
    header('Location: scholarships.php');
    exit;
}

$page_title = 'Apply for Scholarship - ScholarHub';
$base_path  = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>
<style>
  #appModal .modal-content { max-width: 780px; max-height: 90vh; overflow-y: auto; }
  .form-section { margin-bottom: 2rem; }
  .form-section h4 { font-size: 1rem; font-weight: 700; color: #1a1a2e; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #E53935; }
  .form-section .form-group { margin-bottom: 1rem; }
  .form-section .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: #424242; margin-bottom: 0.35rem; }
  .form-section input[type=text],
  .form-section input[type=email],
  .form-section input[type=date],
  .form-section input[type=number],
  .form-section input[type=tel],
  .form-section select,
  .form-section textarea {
    width: 100%; padding: 0.6rem 0.875rem;
    border: 1.5px solid #D1D5DB; border-radius: 8px;
    font-size: 0.875rem; font-family: inherit; color: #1a1a2e;
    background: #fff; transition: border-color 0.2s, box-shadow 0.2s;
  }
  .form-section input:focus, .form-section select:focus, .form-section textarea:focus {
    outline: none; border-color: #E53935; box-shadow: 0 0 0 3px rgba(229,57,53,0.08);
  }
  .form-section textarea { resize: vertical; min-height: 120px; }
  .form-section input[readonly] { background: #F5F5F5; color: #9E9E9E; }
  .form-section input[type=file] { padding: 0.5rem; }
  .form-section .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .form-section .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
  .form-section .form-row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; }
  .radio-group { display: flex; gap: 1.5rem; margin-top: 0.5rem; }
  .radio-group label { display: flex; align-items: center; gap: 0.4rem; font-weight: 400; cursor: pointer; }
  .checkbox-group { margin-top: 0.5rem; }
  .checkbox-group label { display: flex; align-items: center; gap: 0.5rem; font-weight: 400; cursor: pointer; margin-bottom: 0.5rem; }
  @media(max-width:768px){ 
    .form-section .form-row, .form-section .form-row-3, .form-section .form-row-4 { grid-template-columns: 1fr; } 
  }
</style>

<div id="appModal" class="modal" style="display:flex;">
  <div class="modal-content">
    <div class="modal-header">
      <div>
        <h3 style="margin:0 0 0.2rem;">Scholarship Application Form</h3>
        <div style="font-size:0.875rem;color:#E53935;font-weight:600;"><?= htmlspecialchars($selected_scholarship['title']) ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:0.75rem;">
        <?php if ($days_remaining !== null): ?>
          <div class="alert <?= $days_remaining > 7 ? 'alert-success' : 'alert-warning' ?>" style="margin:0;padding:0.35rem 0.75rem;font-size:0.75rem;">
            <?= $days_remaining > 0 ? $days_remaining.' day(s) remaining' : 'Deadline reached' ?>
            &mdash; <?= htmlspecialchars($selected_scholarship['deadline']) ?>
          </div>
        <?php endif; ?>
        <a href="scholarships.php" class="modal-close" style="text-decoration:none;">&times;</a>
      </div>
    </div>

  <?php if ($eligible === false): ?>
    <div class="alert alert-warning" style="margin-bottom:1rem;">
      <strong>Eligibility Notice:</strong> <?= implode('; ', array_map('htmlspecialchars', $eligibility_notes)) ?>
      <br><small>You may still apply but your application could be flagged during screening.</small>
    </div>
  <?php elseif ($eligible === true): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">You appear to meet the basic eligibility requirements.</div>
  <?php endif; ?>

  <form id="appForm" method="POST" action="../controllers/ApplicationController.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="scholarship_id" value="<?= $selected_scholarship['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
    <input type="hidden" name="full_name" id="full_name_hidden">
    <input type="hidden" name="home_address" id="home_address_hidden">

    <!-- Personal Information -->
    <div class="form-section">
      <h4>Personal Information</h4>
      <div class="form-row-3">
        <div class="form-group">
          <label>Last Name *</label>
          <input type="text" id="last_name" required placeholder="Dela Cruz">
        </div>
        <div class="form-group">
          <label>First Name *</label>
          <input type="text" id="first_name" required placeholder="Juan">
        </div>
        <div class="form-group">
          <label>Middle Name</label>
          <input type="text" id="middle_name" placeholder="Santos">
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label>Date of Birth *</label>
          <input type="date" name="dob" id="dob" required>
        </div>
        <div class="form-group">
          <label>Age *</label>
          <input type="number" name="age" id="age" required min="1" max="120" placeholder="18">
        </div>
        <div class="form-group">
          <label>Sex *</label>
          <div class="radio-group">
            <label><input type="radio" name="sex" value="Male" required> Male</label>
            <label><input type="radio" name="sex" value="Female" required> Female</label>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label>Civil Status *</label>
        <select name="civil_status" required>
          <option value="">Select</option>
          <option value="Single">Single</option>
          <option value="Married">Married</option>
          <option value="Widowed">Widowed</option>
          <option value="Separated">Separated</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Contact Number *</label>
          <input type="tel" name="mobile" required placeholder="+63 912 345 6789">
        </div>
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" required placeholder="you@example.com">
        </div>
      </div>
    </div>

    <!-- Home Address -->
    <div class="form-section">
      <h4>Home Address</h4>
      <div class="form-row">
        <div class="form-group">
          <label>Street/House No. *</label>
          <input type="text" id="street" required placeholder="123 Main St">
        </div>
        <div class="form-group">
          <label>Barangay *</label>
          <input type="text" id="barangay" required placeholder="Barangay Name">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>City/Municipality *</label>
          <input type="text" id="city" required placeholder="City Name">
        </div>
        <div class="form-group">
          <label>Province *</label>
          <select id="province" required>
            <option value="">Select Province</option>
            <option value="Abra">Abra</option>
            <option value="Agusan del Norte">Agusan del Norte</option>
            <option value="Agusan del Sur">Agusan del Sur</option>
            <option value="Aklan">Aklan</option>
            <option value="Albay">Albay</option>
            <option value="Antique">Antique</option>
            <option value="Apayao">Apayao</option>
            <option value="Aurora">Aurora</option>
            <option value="Basilan">Basilan</option>
            <option value="Bataan">Bataan</option>
            <option value="Batanes">Batanes</option>
            <option value="Batangas">Batangas</option>
            <option value="Benguet">Benguet</option>
            <option value="Biliran">Biliran</option>
            <option value="Bohol">Bohol</option>
            <option value="Bukidnon">Bukidnon</option>
            <option value="Bulacan">Bulacan</option>
            <option value="Cagayan">Cagayan</option>
            <option value="Camarines Norte">Camarines Norte</option>
            <option value="Camarines Sur">Camarines Sur</option>
            <option value="Camiguin">Camiguin</option>
            <option value="Capiz">Capiz</option>
            <option value="Catanduanes">Catanduanes</option>
            <option value="Cavite">Cavite</option>
            <option value="Cebu">Cebu</option>
            <option value="Cotabato">Cotabato</option>
            <option value="Davao de Oro">Davao de Oro</option>
            <option value="Davao del Norte">Davao del Norte</option>
            <option value="Davao del Sur">Davao del Sur</option>
            <option value="Davao Occidental">Davao Occidental</option>
            <option value="Davao Oriental">Davao Oriental</option>
            <option value="Dinagat Islands">Dinagat Islands</option>
            <option value="Eastern Samar">Eastern Samar</option>
            <option value="Guimaras">Guimaras</option>
            <option value="Ifugao">Ifugao</option>
            <option value="Ilocos Norte">Ilocos Norte</option>
            <option value="Ilocos Sur">Ilocos Sur</option>
            <option value="Iloilo">Iloilo</option>
            <option value="Isabela">Isabela</option>
            <option value="Kalinga">Kalinga</option>
            <option value="La Union">La Union</option>
            <option value="Laguna">Laguna</option>
            <option value="Lanao del Norte">Lanao del Norte</option>
            <option value="Lanao del Sur">Lanao del Sur</option>
            <option value="Leyte">Leyte</option>
            <option value="Maguindanao">Maguindanao</option>
            <option value="Marinduque">Marinduque</option>
            <option value="Masbate">Masbate</option>
            <option value="Metro Manila">Metro Manila</option>
            <option value="Misamis Occidental">Misamis Occidental</option>
            <option value="Misamis Oriental">Misamis Oriental</option>
            <option value="Mountain Province">Mountain Province</option>
            <option value="Negros Occidental">Negros Occidental</option>
            <option value="Negros Oriental">Negros Oriental</option>
            <option value="Northern Samar">Northern Samar</option>
            <option value="Nueva Ecija">Nueva Ecija</option>
            <option value="Nueva Vizcaya">Nueva Vizcaya</option>
            <option value="Occidental Mindoro">Occidental Mindoro</option>
            <option value="Oriental Mindoro">Oriental Mindoro</option>
            <option value="Palawan">Palawan</option>
            <option value="Pampanga">Pampanga</option>
            <option value="Pangasinan">Pangasinan</option>
            <option value="Quezon">Quezon</option>
            <option value="Quirino">Quirino</option>
            <option value="Rizal">Rizal</option>
            <option value="Romblon">Romblon</option>
            <option value="Samar">Samar</option>
            <option value="Sarangani">Sarangani</option>
            <option value="Siquijor">Siquijor</option>
            <option value="Sorsogon">Sorsogon</option>
            <option value="South Cotabato">South Cotabato</option>
            <option value="Southern Leyte">Southern Leyte</option>
            <option value="Sultan Kudarat">Sultan Kudarat</option>
            <option value="Sulu">Sulu</option>
            <option value="Surigao del Norte">Surigao del Norte</option>
            <option value="Surigao del Sur">Surigao del Sur</option>
            <option value="Tarlac">Tarlac</option>
            <option value="Tawi-Tawi">Tawi-Tawi</option>
            <option value="Zambales">Zambales</option>
            <option value="Zamboanga del Norte">Zamboanga del Norte</option>
            <option value="Zamboanga del Sur">Zamboanga del Sur</option>
            <option value="Zamboanga Sibugay">Zamboanga Sibugay</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Family Background -->
    <div class="form-section">
      <h4>Family Background</h4>
      <div class="form-row">
        <div class="form-group">
          <label>Parent/Guardian Name *</label>
          <input type="text" name="parent_name" required placeholder="Parent/Guardian Full Name">
        </div>
        <div class="form-group">
          <label>Occupation *</label>
          <input type="text" name="parent_occupation" required placeholder="Occupation">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Monthly Income *</label>
          <input type="text" name="monthly_income" required placeholder="₱ 15,000">
        </div>
      </div>
    </div>

      <!-- Educational Background -->
    <div class="form-section">
      <h4>Educational Background</h4>
      <div class="form-group">
        <label>School Name *</label>
        <input type="text" name="school_name" required value="St. Cecilia's College-Cebu, Inc." readonly style="background:#F5F5F5;color:#9E9E9E;">
      </div>
      <div class="form-group">
        <label>Program *</label>
        <select name="course_strand" required>
          <option value="">Select Program</option>
          <option value="BSIT">BSIT</option>
          <option value="ACT">ACT</option>
          <option value="BSED">BSED</option>
          <option value="BEED">BEED</option>
          <option value="BSHM">BSHM</option>
          <option value="BSTM">BSTM</option>
          <option value="BSBA">BSBA</option>
          <option value="BSMA">BSMA</option>
          <option value="BSCRIM">BSCRIM</option>
          <option value="BSNURSING">BSNURSING</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>General Average (GWA) *</label>
          <input type="text" name="gwa" required placeholder="92.5">
        </div>
        <div class="form-group">
          <label>Year Level *</label>
          <select name="year_level" required>
            <option value="">Select Year Level</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
            <option value="5th Year">5th Year</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Requirements Upload -->
    <div class="form-section">
      <h4>Requirements Upload</h4>
      <div class="form-row">
        <div class="form-group">
          <label>Certificate of Indigency *</label>
          <input type="file" name="cert_indigency" id="cert_indigency" required accept=".pdf,.jpg,.jpeg,.png">
        </div>
        <div class="form-group">
          <label>2x2 ID Picture *</label>
          <input type="file" name="id_picture" id="id_picture" required accept=".jpg,.jpeg,.png">
        </div>
      </div>
    </div>

    <!-- Declaration -->
    <div class="form-section">
      <h4>Declaration</h4>
      <div class="checkbox-group">
        <label>
          <input type="checkbox" name="declaration" required>
          I confirm that all information provided is true and correct.
        </label>
      </div>
    </div>

      <div class="modal-footer">
        <a href="scholarships.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" name="save_draft" value="1" class="btn btn-secondary">Save Draft</button>
        <button type="submit" class="btn btn-primary">Submit Application</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById('appForm');
  
  // Auto-calculate age from date of birth
  const dobInput = document.getElementById('dob');
  const ageInput = document.getElementById('age');
  
  if (dobInput && ageInput) {
    dobInput.addEventListener('change', function() {
      const dob = new Date(this.value);
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const monthDiff = today.getMonth() - dob.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
      }
      if (age > 0 && age < 120) {
        ageInput.value = age;
      }
    });
  }
  
  // Combine name and address fields before submission
  form.addEventListener('submit', function(e) {
    // Combine name fields
    const lastName = document.getElementById('last_name').value.trim();
    const firstName = document.getElementById('first_name').value.trim();
    const middleName = document.getElementById('middle_name').value.trim();
    const fullName = `${firstName} ${middleName} ${lastName}`.replace(/\s+/g, ' ').trim();
    document.getElementById('full_name_hidden').value = fullName;
    
    // Combine address fields
    const street = document.getElementById('street').value.trim();
    const barangay = document.getElementById('barangay').value.trim();
    const city = document.getElementById('city').value.trim();
    const province = document.getElementById('province').value.trim();
    const homeAddress = `${street}, ${barangay}, ${city}, ${province}`;
    document.getElementById('home_address_hidden').value = homeAddress;
    
    // Validate required files (only for non-draft submissions)
    const isDraft = e.submitter && e.submitter.name === 'save_draft';
    
    if (!isDraft) {
      const requiredFiles = ['cert_indigency', 'id_picture'];
      let missingFiles = [];
      
      for (const fileId of requiredFiles) {
        const fileInput = document.getElementById(fileId);
        if (fileInput && !fileInput.files.length) {
          missingFiles.push(fileInput.previousElementSibling.textContent.replace(' *', ''));
        }
      }
      
      if (missingFiles.length > 0) {
        e.preventDefault();
        alert('Please upload the following required documents:\n\n' + missingFiles.join('\n'));
        return false;
      }
    }
    
    return true;
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
