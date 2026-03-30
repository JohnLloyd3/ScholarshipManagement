<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

requireLogin();
requireRole('student', 'Student access required');

$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Get available scholarships
$stmt = $pdo->query('SELECT * FROM scholarships WHERE status = "open" ORDER BY created_at DESC');
$scholarships = $stmt->fetchAll();

// Load student profile for eligibility checks
$profile = [];
try {
  $ps = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = :uid');
  $ps->execute([':uid' => $user_id]);
  $profile = $ps->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) { $profile = []; }

// Get selected scholarship details
$selected_scholarship = null;
$requirements = [];
$scholarship_id = $_GET['scholarship_id'] ?? 0;

if ($scholarship_id) {
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id AND status = "open"');
    $stmt->execute([':id' => $scholarship_id]);
    $selected_scholarship = $stmt->fetch();
    
    if ($selected_scholarship) {
      $stmt = $pdo->prepare('SELECT requirement, requirement_type, value FROM eligibility_requirements WHERE scholarship_id = :id');
      $stmt->execute([':id' => $scholarship_id]);
      $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Load required documents list
      try {
        $dstmt = $pdo->prepare('SELECT document_name FROM scholarship_documents WHERE scholarship_id = :id');
        $dstmt->execute([':id' => $scholarship_id]);
        $required_documents = $dstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
      } catch (Exception $e) { $required_documents = []; }

      // Deadline countdown
      $days_remaining = null;
      if (!empty($selected_scholarship['deadline'])) {
        $deadline_ts = strtotime($selected_scholarship['deadline']);
        $now = time();
        $diff = $deadline_ts - $now;
        $days_remaining = $diff > 0 ? (int)ceil($diff / 86400) : 0;
      }

      // Basic eligibility checks using student profile
      $eligible = true;
      $eligibility_notes = [];
      if (!empty($requirements)) {
        foreach ($requirements as $r) {
          $rtype = $r['requirement_type'] ?? 'documents';
          $val = $r['value'] ?? '';
          if ($rtype === 'gpa') {
            $min = floatval($val ?: 0);
            $user_gpa = floatval($profile['gpa'] ?? 0);
            if ($user_gpa < $min) {
              $eligible = false;
              $eligibility_notes[] = "Minimum GPA of $min required (yours: $user_gpa)";
            }
          } elseif ($rtype === 'field') {
            $required_field = strtolower($val);
            $user_field = strtolower($profile['course'] ?? '');
            if ($required_field && $required_field !== $user_field) {
              $eligible = false;
              $eligibility_notes[] = "Program requirement: $val";
            }
          } elseif ($rtype === 'enrollment') {
            $en = strtolower($val);
            $user_en = strtolower($profile['enrollment_status'] ?? '');
            if ($en && $en !== $user_en) {
              $eligible = false;
              $eligibility_notes[] = "Enrollment: $val required (yours: " . ($profile['enrollment_status'] ?? 'N/A') . ")";
            }
          }
        }
      }
        
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

// Default containers (only set if not already set inside the if block above)
if (!isset($required_documents)) $required_documents = [];
if (!isset($days_remaining)) $days_remaining = null;
if (!isset($eligible)) $eligible = null;
if (!isset($eligibility_notes)) $eligibility_notes = [];
?>
<?php
$page_title = 'Apply for Scholarship - ScholarHub';
$base_path = '../';
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1>📝 Apply for Scholarship</h1>
  <p class="text-muted">Select a scholarship and complete your application</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-xl);">Available Scholarships</h3>
  <?php if (empty($scholarships)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">🎓</div>
      <h3 class="empty-state-title">No Open Scholarships</h3>
      <p class="empty-state-description">There are no open scholarships available at this time. Check back later!</p>
    </div>
  <?php else: ?>
    <div style="display: grid; gap: var(--space-lg);">
      <?php foreach ($scholarships as $sch): ?>
        <div class="card <?= $selected_scholarship && $selected_scholarship['id'] == $sch['id'] ? 'selected' : '' ?>" 
             onclick="window.location.href='?scholarship_id=<?= $sch['id'] ?>'"
             style="cursor: pointer; <?= $selected_scholarship && $selected_scholarship['id'] == $sch['id'] ? 'border: 2px solid var(--red-primary); background: var(--red-ghost);' : '' ?>">
          <div class="card-header">
            <h4 class="card-title"><?= htmlspecialchars($sch['title']) ?></h4>
          </div>
          <div class="card-body">
            <p><strong>Organization:</strong> <?= htmlspecialchars($sch['organization'] ?? 'N/A') ?></p>
            <p><?= htmlspecialchars(substr($sch['description'] ?? '', 0, 150)) ?>...</p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($selected_scholarship): ?>
  <div class="content-card" style="margin-top: var(--space-xl);">
    <h3 style="margin-bottom: var(--space-xl);">Application Form: <?= htmlspecialchars($selected_scholarship['title']) ?></h3>
    
    <?php if (!empty($requirements)): ?>
      <div class="alert alert-info" style="margin-bottom: var(--space-lg);">
        <strong>📋 Eligibility Requirements:</strong>
        <ul style="margin: var(--space-sm) 0 0 var(--space-lg); padding: 0;">
          <?php foreach ($requirements as $req): ?>
            <?php if (is_array($req)): ?>
              <li><?= htmlspecialchars($req['requirement'] ?? ($req['value'] ?? '')) ?></li>
            <?php else: ?>
              <li><?= htmlspecialchars($req) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!empty($required_documents)): ?>
      <div class="alert alert-info" style="margin-bottom: var(--space-lg);">
        <strong>📄 Required Documents:</strong>
        <ul style="margin: var(--space-sm) 0 0 var(--space-lg); padding: 0;">
          <?php foreach ($required_documents as $rd): ?>
            <li><?= htmlspecialchars($rd) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (isset($days_remaining)): ?>
      <div class="alert <?= $days_remaining > 7 ? 'alert-success' : 'alert-warning' ?>" style="margin-bottom: var(--space-lg);">
        <strong>⏰ Deadline:</strong>
        <?php if ($days_remaining > 0): ?>
          <?= (int)$days_remaining ?> day(s) remaining (<?= htmlspecialchars($selected_scholarship['deadline']) ?>)
        <?php else: ?>
          Deadline reached (<?= htmlspecialchars($selected_scholarship['deadline']) ?>)
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($eligible) && $eligible === false): ?>
      <div class="alert alert-warning" style="margin-bottom: var(--space-lg);">
        <strong>⚠️ Eligibility Notice:</strong>
        <ul style="margin: var(--space-sm) 0 0 var(--space-lg); padding: 0;">
          <?php foreach ($eligibility_notes as $note): ?>
            <li><?= htmlspecialchars($note) ?></li>
          <?php endforeach; ?>
        </ul>
        <small>You may still apply, but your application could be flagged during screening.</small>
      </div>
    <?php elseif (isset($eligible) && $eligible === true): ?>
      <div class="alert alert-success" style="margin-bottom: var(--space-lg);">
        <strong>✅ Eligibility:</strong> You appear to meet basic eligibility requirements.
      </div>
    <?php endif; ?>
          <form id="appForm" method="POST" action="../controllers/ApplicationController.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="scholarship_id" value="<?= $selected_scholarship['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">

            <div id="stepProgress" style="display:flex;gap:.5rem;margin-bottom:12px;align-items:center">
              <?php $stepCount = 7; for($i=1;$i<=$stepCount;$i++): ?>
                <div class="step-dot" data-step="<?= $i ?>" style="flex:1;padding:.4rem;border-radius:6px;text-align:center;background:#f3f4f6;color:#6b7280;font-weight:600">Step <?= $i ?></div>
              <?php endfor; ?>
            </div>

            <!-- Multi-step: each .step is one page -->
            <!-- I. Personal Information -->
            <div class="form-section step">
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
            <div class="form-section step">
              <h4>II. Senior High School Information</h4>
              <div class="form-group"><label>Name of Senior High School *</label><input type="text" name="shs_name" required></div>
              <div class="form-group"><label>School Address *</label><input type="text" name="shs_address" required></div>
              <div class="form-group"><label>Strand Taken *</label><input type="text" name="strand" required placeholder="STEM, ABM, HUMSS, TVL, GAS, etc."></div>
              <div class="form-group"><label>General Weighted Average (GWA) *</label><input type="text" name="gwa" required></div>
              <div class="form-group"><label>Year Graduated *</label><input type="text" name="year_graduated" required></div>
            </div>

            <!-- III. College Enrollment Information -->
            <div class="form-section step">
              <h4>III. College Enrollment Information</h4>
              <div class="form-group"><label>Intended College/University *</label><input type="text" name="intended_college" required></div>
              <div class="form-group"><label>Course/Degree Program *</label><input type="text" name="course_program" required></div>
              <div class="form-group"><label>Type of Institution *</label><select name="institution_type" required><option value="">Select</option><option value="Public">Public</option><option value="Private">Private</option></select></div>
              <div class="form-group"><label>Admission Letter? *</label><select name="admission_letter" required><option value="">Select</option><option value="Yes">Yes</option><option value="No">No</option></select></div>
              <div class="form-group"><label>Expected Enrollment Date *</label><input type="date" name="enrollment_date" required></div>
            </div>

            <!-- IV. Family Background -->
            <div class="form-section step">
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
            <div class="form-section step">
              <h4>V. Scholarship Details</h4>
              <div class="form-group"><label>Scholarship Applying For</label><input type="text" name="scholarship_title" value="<?= htmlspecialchars($selected_scholarship['title']) ?>" readonly></div>
              <div class="form-group"><label>Receiving another scholarship?</label><select name="receiving_other"><option value="No">No</option><option value="Yes">Yes</option></select></div>
              <div class="form-group"><label>If yes, specify</label><input type="text" name="other_scholarship_details"></div>
            </div>

            <!-- VI. Required Documents -->
            <div class="form-section step">
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
            <div class="form-section step">
              <h4>VII. Applicant’s Declaration</h4>
              <p>I certify that the information provided is true and correct. I understand that providing false information may result in disqualification from the scholarship program.</p>
              <div class="form-group"><label>Applicant’s Name & Signature *</label><input type="text" name="applicant_signature" required></div>
              <div class="form-group"><label>Date *</label><input type="date" name="applicant_date" required></div>
            </div>

            <div style="display:flex;gap:var(--space-md);align-items:center;margin-top:var(--space-xl);">
              <button type="button" id="prevBtn" class="btn btn-secondary" style="display:none;">← Previous</button>
              <button type="button" id="nextBtn" class="btn btn-primary">Next →</button>
              <button type="submit" name="save_draft" value="1" class="btn btn-ghost">💾 Save Draft</button>
              <button type="submit" class="btn btn-primary">✅ Submit Application</button>
              <a href="apply_scholarship.php" style="margin-left:var(--space-md)" class="text-muted">Cancel</a>
            </div>
          </form>

          <script>
            (function(){
              const steps = Array.from(document.querySelectorAll('.step'));
              const dots = Array.from(document.querySelectorAll('#stepProgress .step-dot'));
              const form = document.getElementById('appForm');
              let cur = 0;

              function updateProgress(i){
                dots.forEach((d,idx)=>{
                  d.style.background = idx<=i ? '#b91c1c' : '#f3f4f6';
                  d.style.color = idx<=i ? '#fff' : '#6b7280';
                });
              }

              function show(i){
                steps.forEach((s,idx)=> s.style.display = idx===i ? 'block' : 'none');
                updateProgress(i);
                prevBtn.style.display = i>0 ? 'inline-block' : 'none';
                nextBtn.style.display = i < steps.length-1 ? 'inline-block' : 'none';
              }

              function validateStep(i){
                const step = steps[i];
                const required = Array.from(step.querySelectorAll('[required]'));
                for (const el of required){
                  if (el.type === 'checkbox' || el.type === 'radio'){
                    // ensure at least one in group checked
                    const name = el.name;
                    const group = step.querySelectorAll('[name="'+name+'"]');
                    let ok = false;
                    group.forEach(g=>{ if (g.checked) ok = true; });
                    if (!ok) { el.focus(); return {valid:false, message:'Please complete required fields.'}; }
                  } else if (el.type === 'file'){
                    if (el.required && el.files.length === 0){ el.focus(); return {valid:false, message:'Please attach required files.'}; }
                  } else if (!el.value || el.value.trim() === ''){
                    el.focus();
                    return {valid:false, message:'Please fill the required field: ' + (el.previousElementSibling ? el.previousElementSibling.innerText : el.name)};
                  }
                }
                return {valid:true};
              }

              const prevBtn = document.getElementById('prevBtn');
              const nextBtn = document.getElementById('nextBtn');

              prevBtn.addEventListener('click', ()=>{ if(cur>0){ cur--; show(cur); } });
              nextBtn.addEventListener('click', ()=>{
                const v = validateStep(cur);
                if (!v.valid){ alert(v.message); return; }
                if(cur<steps.length-1){ cur++; show(cur); }
              });

              // Final submit: validate all required fields across steps
              form.addEventListener('submit', function(e){
                // If user clicked Save Draft (name save_draft present) allow partial
                const fm = new FormData(form);
                if (fm.get('save_draft')) return true;
                for(let i=0;i<steps.length;i++){
                  const v = validateStep(i);
                  if (!v.valid){ e.preventDefault(); alert(v.message); show(i); cur = i; return false; }
                }
                return true;
              });

              // initialize
              show(cur);
            })();
          </script>
    </form>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
