<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

requireLogin();
requireRole('admin', 'Admin access required');
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Ensure user session data is populated
if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
    $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, role FROM users WHERE id = :id');
    $stmt->execute([':id' => $user_id]);
    $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sessionUser) {
        $_SESSION['user'] = $sessionUser;
    }
}

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
        $_SESSION['flash'] = 'Invalid request (CSRF token missing or incorrect).';
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

    $first_name = sanitizeString($_POST['first_name'] ?? '');
    $last_name = sanitizeString($_POST['last_name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : null;
    $phone = sanitizeString($_POST['phone'] ?? '');
    $address = sanitizeString($_POST['address'] ?? '');

    if (!$first_name || !$last_name || !$email) {
        $_SESSION['flash'] = 'Please provide your first name, last name and a valid email.';
        header('Location: profile.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE users SET first_name = :fn, last_name = :ln, email = :email, phone = :phone, address = :address WHERE id = :id');
        $stmt->execute([
            ':fn' => $first_name,
            ':ln' => $last_name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':id' => $user_id
        ]);

        $_SESSION['success'] = 'Profile updated successfully!';
    } catch (Exception $e) {
        $_SESSION['flash'] = 'Failed to update profile: ' . $e->getMessage();
    }
    header('Location: profile.php');
    exit;
}

// Load user
$stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, phone, address, role, profile_picture, created_at FROM users WHERE id = :id');
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found, show error but don't destroy session yet
if (!$user || !is_array($user)) {
    // Try to get user info from session as fallback
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $user = $_SESSION['user'];
        // Add missing fields with defaults
        $user['phone'] = $user['phone'] ?? '';
        $user['address'] = $user['address'] ?? '';
        $user['profile_picture'] = $user['profile_picture'] ?? '';
        $user['created_at'] = $user['created_at'] ?? date('Y-m-d H:i:s');
    } else {
        // Only destroy session if we really can't find the user
        $_SESSION['flash'] = 'Unable to load profile. Please try logging in again.';
        header('Location: ../auth/logout.php');
        exit;
    }
}
?>
<?php
$page_title = 'My Profile - Admin';
$base_path = '../';
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
          <i class="fas fa-crown"></i> <?= htmlspecialchars(ucfirst($user['role'] ?? 'Admin')) ?>
        </span>
      </p>
      <form method="POST" enctype="multipart/form-data" style="margin-top: var(--space-md);">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    
    <div class="form-group">
      <label class="form-label">Username</label>
      <input type="text" class="form-input" value="<?= htmlspecialchars($user['username'] ?? '') ?>" disabled>
      <small class="text-muted">Username cannot be changed</small>
    </div>

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

    <div class="form-group">
      <label class="form-label">Address</label>
      <textarea name="address" class="form-input" rows="3" placeholder="Enter your address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Member Since</label>
      <input type="text" class="form-input" value="<?= date('F d, Y', strtotime($user['created_at'])) ?>" disabled>
    </div>

    <div style="display: flex; gap: var(--space-md); margin-top: var(--space-xl);">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
      <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>

<div class="content-card" style="margin-top: var(--space-xl);">
  <h3 style="margin-bottom: var(--space-xl);">Change Password</h3>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
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
