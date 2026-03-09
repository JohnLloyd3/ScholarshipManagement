<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

requireLogin();
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        $_SESSION['success'] = 'Profile updated.';
    } catch (Exception $e) {
        $_SESSION['flash'] = 'Failed to update profile.';
    }
    header('Location: profile.php'); exit;
}

// Load user and profile
$stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, phone FROM users WHERE id = :id');
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profile = [];
$ps = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = :uid');
$ps->execute([':uid' => $user_id]);
$profile = $ps->fetch(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Profile</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}</style>
</head>
<body>
  <div class="container" style="max-width:900px;margin:40px auto">
    <h2>My Profile</h2>
    <?php if (!empty($_SESSION['success'])): ?><div class="message success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (!empty($_SESSION['flash'])): ?><div class="message error"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>

    <form method="POST">
      <div class="form-row">
        <div>
          <label>First Name</label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
        </div>
        <div>
          <label>Last Name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
        </div>
      </div>
      <div style="margin-top:12px">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
      </div>
      <div style="margin-top:12px">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
      </div>
      <h3 style="margin-top:18px">Student Profile</h3>
      <div class="form-row">
        <div>
          <label>GPA</label>
          <input type="number" step="0.01" name="gpa" value="<?= htmlspecialchars($profile['gpa'] ?? '') ?>">
        </div>
        <div>
          <label>Course / Program</label>
          <input type="text" name="course" value="<?= htmlspecialchars($profile['course'] ?? '') ?>">
        </div>
      </div>
      <div style="margin-top:12px">
        <label>University</label>
        <input type="text" name="university" value="<?= htmlspecialchars($profile['university'] ?? '') ?>">
      </div>
      <div style="margin-top:12px">
        <label>Enrollment Status</label>
        <select name="enrollment_status">
          <option value="full-time"<?= (isset($profile['enrollment_status'])&&$profile['enrollment_status']=='full-time')?' selected':'' ?>>Full-time</option>
          <option value="part-time"<?= (isset($profile['enrollment_status'])&&$profile['enrollment_status']=='part-time')?' selected':'' ?>>Part-time</option>
          <option value="graduated"<?= (isset($profile['enrollment_status'])&&$profile['enrollment_status']=='graduated')?' selected':'' ?>>Graduated</option>
        </select>
      </div>
      <div style="margin-top:18px">
        <button class="btn btn-primary">Save Profile</button>
        <a href="dashboard_new.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</body>
</html>
