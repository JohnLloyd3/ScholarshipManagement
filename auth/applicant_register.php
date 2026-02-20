<?php
// Applicant Registration Form
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $errors = [];
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
        // Insert into students table
        $stmt = $pdo->prepare('INSERT INTO students (user_id, first_name, last_name, email, phone, address, gpa, enrollment_status) VALUES (:user_id, :first_name, :last_name, :email, :phone, :address, :gpa, :enrollment_status)');
        $stmt->execute([
            'user_id' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $contact_number,
            'address' => $mailing_address,
            'gpa' => $report_card_average,
            'enrollment_status' => 'full-time'
        ]);
        header('Location: login.php?registered=1');
        exit;
    }
}
?>
<!doctype html>
<html>
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Applicant Registration</title>
        <link rel="stylesheet" href="../assets/style.css">
        <style>
            body { background: #f7f7fa; }
            .main { max-width: 750px; margin: 40px auto; background: #fff; padding: 32px; border-radius: 10px; box-shadow: 0 2px 16px #e0e0e0; }
            h2 { text-align: center; margin-bottom: 30px; font-size: 2.2rem; }
            fieldset { margin-bottom: 28px; padding: 22px 18px 12px 18px; border: 1.5px solid #e3e3e3; border-radius: 8px; background: #fafbfc; }
            legend { font-weight: bold; font-size: 1.1rem; color: #333; padding: 0 8px; }
            .form-group { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
            .form-group label { flex: 0 0 210px; margin-bottom: 0; font-weight: 500; color: #222; font-size: 1rem; }
            .form-group input, .form-group select, .form-group textarea {
                flex: 1 1 320px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem;
                background: #fff; margin-left: 10px; min-width: 0;
            }
            .form-group textarea { resize: vertical; min-height: 38px; }
            .btn { display: block; width: 100%; background: #4CAF50; color: #fff; border: none; padding: 13px 0; border-radius: 6px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 18px; transition: background 0.2s; }
            .btn:hover { background: #388e3c; }
            .flash.error-flash { background: #ffeaea; color: #b71c1c; border: 1px solid #ffcdd2; padding: 10px 18px; border-radius: 6px; margin-bottom: 18px; }
            @media (max-width: 700px) {
                .main { padding: 10px; }
                .form-group { flex-direction: column; align-items: stretch; }
                .form-group label { margin-bottom: 6px; }
                .form-group input, .form-group select, .form-group textarea { margin-left: 0; }
            }
        </style>
</head>
<body>
    <div class="main">
        <h2>Applicant Registration</h2>
        <?php if (!empty($errors)): ?>
            <div class="flash error-flash">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <fieldset style="margin-bottom:20px;padding:15px;border:1px solid #eee;border-radius:6px;">
                <legend><b>Account Information</b></legend>
                <div class="form-group"><label>Username *</label><input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
                <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
            </fieldset>
            <fieldset style="margin-bottom:20px;padding:15px;border:1px solid #eee;border-radius:6px;">
                <legend><b>Personal Information</b></legend>
                <div class="form-group"><label>First Name *</label><input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Age *</label><input type="number" name="age" min="1" required value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"></div>
                <div class="form-group"><label>Sex *</label><select name="sex" required><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
                <div class="form-group"><label>Status *</label><input type="text" name="status" required value="<?= htmlspecialchars($_POST['status'] ?? '') ?>"></div>
                <div class="form-group"><label>Religion</label><input type="text" name="religion" value="<?= htmlspecialchars($_POST['religion'] ?? '') ?>"></div>
                <div class="form-group"><label>Citizenship *</label><input type="text" name="citizenship" required value="<?= htmlspecialchars($_POST['citizenship'] ?? '') ?>"></div>
                <div class="form-group"><label>Date of Birth *</label><input type="date" name="dob" required value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>"></div>
                <div class="form-group"><label>Place of Birth *</label><input type="text" name="birth_place" required value="<?= htmlspecialchars($_POST['birth_place'] ?? '') ?>"></div>
                <div class="form-group"><label>Complete Mailing Address *</label><input type="text" name="mailing_address" required value="<?= htmlspecialchars($_POST['mailing_address'] ?? '') ?>"></div>
                <div class="form-group"><label>Home/Provincial Address</label><input type="text" name="provincial_address" value="<?= htmlspecialchars($_POST['provincial_address'] ?? '') ?>"></div>
                <div class="form-group"><label>Tel./Mobile Number *</label><input type="text" name="contact_number" required value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"></div>
            </fieldset>
            <fieldset style="margin-bottom:20px;padding:15px;border:1px solid #eee;border-radius:6px;">
                <legend><b>Family Background</b></legend>
                <div class="form-group"><label>Father's Name</label><input type="text" name="father_name" value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Father's Status</label><select name="father_status"><option value="Living">Living</option><option value="Deceased">Deceased</option></select></div>
                <div class="form-group"><label>Father's Occupation</label><input type="text" name="father_occupation" value="<?= htmlspecialchars($_POST['father_occupation'] ?? '') ?>"></div>
                <div class="form-group"><label>Mother's Name</label><input type="text" name="mother_name" value="<?= htmlspecialchars($_POST['mother_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Mother's Status</label><select name="mother_status"><option value="Living">Living</option><option value="Deceased">Deceased</option></select></div>
                <div class="form-group"><label>Mother's Occupation</label><input type="text" name="mother_occupation" value="<?= htmlspecialchars($_POST['mother_occupation'] ?? '') ?>"></div>
                <div class="form-group"><label>Total Parents' Gross Income</label><input type="number" name="gross_income" min="0" value="<?= htmlspecialchars($_POST['gross_income'] ?? '') ?>"></div>
                <div class="form-group"><label>Brothers/Sisters Enjoying Scholarship</label><textarea name="siblings_scholarship" rows="2"><?= htmlspecialchars($_POST['siblings_scholarship'] ?? '') ?></textarea></div>
                <div class="form-group"><label>Number of children in the family</label><input type="number" name="children_count" min="1" value="<?= htmlspecialchars($_POST['children_count'] ?? '') ?>"></div>
            </fieldset>
            <fieldset style="margin-bottom:20px;padding:15px;border:1px solid #eee;border-radius:6px;">
                <legend><b>Academic Information</b></legend>
                <div class="form-group"><label>School Name (High School) *</label><input type="text" name="school_name" required value="<?= htmlspecialchars($_POST['school_name'] ?? '') ?>"></div>
                <div class="form-group"><label>School Address *</label><input type="text" name="school_address" required value="<?= htmlspecialchars($_POST['school_address'] ?? '') ?>"></div>
                <div class="form-group"><label>School Type *</label><select name="school_type" required><option value="">Select</option><option value="Public">Public</option><option value="Private">Private</option><option value="Vocational">Vocational</option></select></div>
                <div class="form-group"><label>Highest Grade/Year *</label><input type="text" name="highest_grade" required value="<?= htmlspecialchars($_POST['highest_grade'] ?? '') ?>"></div>
                <div class="form-group"><label>Date of Graduation *</label><input type="date" name="graduation_date" required value="<?= htmlspecialchars($_POST['graduation_date'] ?? '') ?>"></div>
                <div class="form-group"><label>Report Card Average *</label><input type="text" name="report_card_average" required value="<?= htmlspecialchars($_POST['report_card_average'] ?? '') ?>"></div>
                <div class="form-group"><label>Rank in Graduating Class</label><input type="text" name="class_rank" value="<?= htmlspecialchars($_POST['class_rank'] ?? '') ?>"></div>
                <div class="form-group"><label>Academic Awards/Honors Received</label><textarea name="awards" rows="2"><?= htmlspecialchars($_POST['awards'] ?? '') ?></textarea></div>
            </fieldset>
            <fieldset style="margin-bottom:20px;padding:15px;border:1px solid #eee;border-radius:6px;">
                <legend><b>Parent/Legal Guardian Declaration</b></legend>
                <div class="form-group">
                  <textarea name="parent_declaration" rows="4" readonly>I/We hereby certify to the truthfulness and completeness of information provided. Any misinformation will automatically disqualify my/our child from the Scholarship Program. I/We are also willing to refund all financial benefits received plus the interest if such misinformation is discovered after my/our child accepted the reward. In connection with this application for financial aide, I/we hereby authorize the Scholarship Committee to conduct a background check on the family finances and to visit our family dwelling.</textarea>
                </div>
                <div class="form-group"><label>Parent/Guardian Name *</label><input type="text" name="guardian_name" required value="<?= htmlspecialchars($_POST['guardian_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Signature *</label><input type="text" name="guardian_signature" required value="<?= htmlspecialchars($_POST['guardian_signature'] ?? '') ?>"></div>
                <div class="form-group"><label>Date *</label><input type="date" name="guardian_date" required value="<?= htmlspecialchars($_POST['guardian_date'] ?? '') ?>"></div>
            </fieldset>
            <button type="submit" class="btn">Register</button>
        </form>
    </div>
</body>
</html>
