<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

requireLogin();
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: profile.php');
        exit;
    }
    $file = $_FILES['profile_picture'];
    
    $validation = validateFileUpload($file, ['image/jpeg', 'image/png', 'image/gif'], 2 * 1024 * 1024);
    if (!$validation['valid']) {
        $_SESSION['flash'] = $validation['error'] ?? 'Invalid file upload.';
        header('Location: profile.php');
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_filename = 'profile_' . $user_id . '_' . time() . '_' . generateSafeFileName($file['name']);
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $stmt = $pdo->prepare('UPDATE users SET profile_picture = :pic WHERE id = :id');
        $stmt->execute([':pic' => 'uploads/profiles/' . $new_filename, ':id' => $user_id]);
        $_SESSION['success'] = 'Profile picture updated successfully!';
    } else {
        $_SESSION['flash'] = 'Failed to upload profile picture.';
    }
    header('Location: profile.php');
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request. Please try again.';
        header('Location: profile.php');
        exit;
    }
    // Password change
    if (!empty($_POST['current_password'])) {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute([':id' => $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!password_verify($_POST['current_password'], $row['password'] ?? '')) {
            $_SESSION['flash'] = 'Current password is incorrect.';
            header('Location: profile.php'); exit;
        }
        $new_pw = $_POST['new_password'] ?? '';
        if (strlen($new_pw) < 8) {
            $_SESSION['flash'] = 'New password must be at least 8 characters.';
            header('Location: profile.php'); exit;
        }
        if ($new_pw !== ($_POST['confirm_new_password'] ?? '')) {
            $_SESSION['flash'] = 'New passwords do not match.';
            header('Location: profile.php'); exit;
        }
        $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id')
            ->execute([':pw' => password_hash($new_pw, PASSWORD_BCRYPT), ':id' => $user_id]);
        $_SESSION['success'] = 'Password changed successfully!';
        header('Location: profile.php'); exit;
    }

    $name = sanitizeString($_POST['first_name'] ?? '');
    $last = sanitizeString($_POST['last_name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : null;
    $phone = sanitizeString($_POST['phone'] ?? '');

    if (!$name || !$last || !$email) {
        $_SESSION['flash'] = 'Please provide your first name, last name and a valid email.';
        header('Location: profile.php'); exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET first_name = :fn, last_name = :ln, email = :email, phone = :phone WHERE id = :id');
        $stmt->execute([':fn' => $name, ':ln' => $last, ':email' => $email, ':phone' => $phone, ':id' => $user_id]);

        // update or insert student_profile
        $gpa = is_numeric($_POST['gpa'] ?? null) ? floatval($_POST['gpa']) : null;
        $course = sanitizeString($_POST['course'] ?? '');
        $uni = sanitizeString($_POST['university'] ?? '');
        $enroll = in_array($_POST['enrollment_status'] ?? '', ['full-time','part-time','graduated']) ? $_POST['enrollment_status'] : null;

        $ps = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id = :uid');
        $ps->execute([':uid' => $user_id]);
        if ($ps->fetch()) {
            $up = $pdo->prepare('UPDATE student_profiles SET gpa = :gpa, course = :course, university = :uni, enrollment_status = :enroll, updated_at = NOW() WHERE user_id = :uid');
            $up->execute([':gpa' => $gpa, ':course' => $course, ':uni' => $uni, ':enroll' => $enroll, ':uid' => $user_id]);
        } else {
            $ins = $pdo->prepare('INSERT INTO student_profiles (user_id, gpa, course, university, enrollment_status) VALUES (:uid, :gpa, :course, :uni, :enroll)');
            $ins->execute([':uid' => $user_id, ':gpa' => $gpa, ':course' => $course, ':uni' => $uni, ':enroll' => $enroll]);
        }

        $_SESSION['success'] = 'Profile updated successfully!';
    } catch (Exception $e) {
        $_SESSION['flash'] = 'Failed to update profile.';
    }
    header('Location: profile.php'); exit;
}

// Load user and profile
$stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, phone, role, profile_picture FROM users WHERE id = :id');
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profile = [];
$ps = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = :uid');
$ps->execute([':uid' => $user_id]);
$profile = $ps->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<?php
$page_title = 'My Profile - ScholarHub';
$base_path = '../';
$csrf_token = generateCSRFToken();
require_once __DIR__ . '/../includes/modern-header.php';
require_once __DIR__ . '/../includes/modern-sidebar.php';
?>

<div class="page-header">
  <h1><i class="fas fa-user"></i> My Profile</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<!-- Profile Picture Card -->
<div class="content-card" style="margin-bottom: var(--space-xl);">
  <div style="display: flex; align-items: center; gap: var(--space-xl);">
    <div style="position: relative;">
      <?php 
      $profile_pic = !empty($user['profile_picture']) && file_exists(__DIR__ . '/../' . $user['profile_picture']) 
        ? '../' . $user['profile_picture'] 
        : '../assets/image/default-avatar.svg';
      ?>
      <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" 
           style="width: 120px; height: 120px; border-radius: var(--r-lg); object-fit: cover; border: 3px solid var(--red-primary); box-shadow: var(--shadow-md);">
    </div>
    <div style="flex: 1;">
      <h2 style="margin: 0 0 var(--space-xs) 0; font-size: 1.5rem; color: var(--gray-900);">
        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
      </h2>
      <p style="margin: 0 0 var(--space-sm) 0; color: var(--gray-600); font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
        <span style="display: inline-block; background: var(--peach-ghost); color: var(--peach); padding: 4px 12px; border-radius: var(--r-md);">
          <?= htmlspecialchars(ucfirst($user['role'] ?? 'Student')) ?>
        </span>
      </p>
      <form method="POST" enctype="multipart/form-data" style="margin-top: var(--space-md);">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="file" name="profile_picture" accept="image/*" id="profilePicInput" style="display: none;" onchange="this.form.submit()">
        <label for="profilePicInput" class="btn btn-primary btn-sm" style="cursor: pointer;">
          <i class="fas fa-camera"></i> Change Photo
        </label>
        <small class="text-muted" style="display: block; margin-top: var(--space-xs);">JPG, PNG or GIF (Max 2MB)</small>
      </form>
    </div>
  </div>
</div>

<div class="content-card">
  <h3 style="margin-bottom: var(--space-xl);">Personal Information</h3>
  
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="grid-2" style="margin-bottom: var(--space-lg);">
      <div class="form-group">
        <label class="form-label">First Name *</label>
        <input type="text" name="first_name" class="form-input" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Last Name *</label>
        <input type="text" name="last_name" class="form-input" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Email Address *</label>
      <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
    </div>

    <div class="form-group">
      <label class="form-label">Phone Number</label>
      <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+63 912 345 6789">
    </div>

    <hr style="margin: var(--space-2xl) 0; border: none; border-top: 1px solid var(--gray-200);">

    <h3 style="margin-bottom: var(--space-xl);">Academic Information</h3>

    <div class="grid-2" style="margin-bottom: var(--space-lg);">
      <div class="form-group">
        <label class="form-label">GPA</label>
        <input type="number" step="0.01" name="gpa" class="form-input" value="<?= htmlspecialchars($profile['gpa'] ?? '') ?>" placeholder="3.50">
      </div>
      <div class="form-group">
        <label class="form-label">Course / Program</label>
        <input type="text" name="course" class="form-input" value="<?= htmlspecialchars($profile['course'] ?? '') ?>" placeholder="Computer Science">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">University</label>
      <input type="text" name="university" class="form-input" value="<?= htmlspecialchars($profile['university'] ?? '') ?>" placeholder="University Name">
    </div>

    <div class="form-group">
      <label class="form-label">Enrollment Status</label>
      <select name="enrollment_status" class="form-select">
        <option value="full-time"<?= (isset($profile['enrollment_status'])&&$profile['enrollment_status']=='full-time')?' selected':'' ?>>Full-time</option>
        <option value="part-time"<?= (isset($profile['enrollment_status'])&&$profile['enrollment_status']=='part-time')?' selected':'' ?>>Part-time</option>
        <option value="graduated"<?= (isset($profile['enrollment_status'])&&$profile['enrollment_status']=='graduated')?' selected':'' ?>>Graduated</option>
      </select>
    </div>

    <div style="display: flex; gap: var(--space-md); margin-top: var(--space-xl);">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
      <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<div class="content-card" style="margin-top: var(--space-xl);">
  <h3 style="margin-bottom: var(--space-xl);">Change Password</h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="form-group">
      <label class="form-label">Current Password *</label>
      <input type="password" name="current_password" class="form-input" required placeholder="Enter current password">
    </div>
    <div class="form-group">
      <label class="form-label">New Password * <small>(min 8 characters)</small></label>
      <input type="password" name="new_password" class="form-input" required minlength="8" placeholder="Enter new password">
    </div>
    <div class="form-group">
      <label class="form-label">Confirm New Password *</label>
      <input type="password" name="confirm_new_password" class="form-input" required minlength="8" placeholder="Repeat new password">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> Update Password</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/modern-footer.php'; ?>
