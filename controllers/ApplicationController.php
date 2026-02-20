<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please log in to submit an application.';
    header('Location: ../auth/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

if ($action === 'create') {
    $scholarship_id = (int)($_POST['scholarship_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $gpa = trim($_POST['gpa'] ?? '');
    $full_time = isset($_POST['full_time']) ? 1 : 0;
    $other_info = trim($_POST['other_info'] ?? '');

    // 3.3 Validate application entries
    $validation_errors = [];
    if (!$scholarship_id) {
        $validation_errors[] = 'Please select a scholarship.';
    }
    if ($title === '') {
        $validation_errors[] = 'Application title is required.';
    }
    if ($gpa === '') {
        $validation_errors[] = 'GPA is required.';
    } elseif (!is_numeric($gpa) || (floatval($gpa) < 0 || floatval($gpa) > 4.0)) {
        $validation_errors[] = 'GPA must be a number between 0 and 4.0.';
    }
    if (!empty($validation_errors)) {
        $_SESSION['flash'] = implode(' ', $validation_errors);
        header('Location: ../member/apply_scholarship.php' . ($scholarship_id ? '?scholarship_id=' . $scholarship_id : ''));
        exit;
    }

    // Check if scholarship exists and is open
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id AND status = "open"');
    $stmt->execute([':id' => $scholarship_id]);
    $scholarship = $stmt->fetch();

    if (!$scholarship) {
        $_SESSION['flash'] = 'Scholarship not found or is closed.';
        header('Location: ../member/apply_scholarship.php');
        exit;
    }

    // Check for duplicate application
    $stmt = $pdo->prepare('SELECT id FROM applications WHERE user_id = :uid AND scholarship_id = :sid');
    $stmt->execute([':uid' => $user_id, ':sid' => $scholarship_id]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = 'You have already applied for this scholarship.';
        header('Location: ../member/apply_scholarship.php');
        exit;
    }

    // Validate requirements
    $stmt = $pdo->prepare('SELECT requirement FROM eligibility_requirements WHERE scholarship_id = :id');
    $stmt->execute([':id' => $scholarship_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $validation_errors = [];
    foreach ($requirements as $req) {
        $req_lower = strtolower($req);
        if (strpos($req_lower, 'gpa') !== false) {
            // Extract GPA requirement (e.g., "GPA >= 3.5")
            if (preg_match('/([0-9.]+)/', $req, $matches)) {
                $required_gpa = (float)$matches[1];
                $user_gpa = (float)$gpa;
                if (strpos($req_lower, '>=') !== false && $user_gpa < $required_gpa) {
                    $validation_errors[] = "GPA requirement not met: $req (Your GPA: $gpa)";
                } elseif (strpos($req_lower, '>') !== false && $user_gpa <= $required_gpa) {
                    $validation_errors[] = "GPA requirement not met: $req (Your GPA: $gpa)";
                }
            }
        }
        if (strpos($req_lower, 'full-time') !== false || strpos($req_lower, 'full time') !== false) {
            if (!$full_time) {
                $validation_errors[] = "Must be enrolled full-time";
            }
        }
    }

    if (!empty($validation_errors)) {
        $_SESSION['flash'] = 'Requirements not met: ' . implode(', ', $validation_errors);
        header('Location: ../member/apply_scholarship.php?scholarship_id=' . $scholarship_id);
        exit;
    }

    $documentPath = null;
    if (!empty($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $up = __DIR__ . '/../uploads';
        if (!is_dir($up)) mkdir($up, 0777, true);
        $name = basename($_FILES['document']['name']);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
        $target = $up . '/' . $safe;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
            $documentPath = 'uploads/' . $safe;
        }
    }

    // Use scholarship title if no custom title provided
    if (empty($title)) {
        $title = $scholarship['title'] . ' Application';
    }

    $details_full = "GPA: $gpa\nFull-time: " . ($full_time ? 'Yes' : 'No');
    if ($other_info) {
        $details_full .= "\nAdditional Info: $other_info";
    }
    if ($details) {
        $details_full .= "\n\n$details";
    }

    // Capture applicant email for easier reporting/filters
    $email = $_SESSION['user']['email'] ?? null;

    $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, title, details, document, status, email) VALUES (:uid, :sid, :title, :details, :doc, :status, :email)');
    $stmt->execute([
        ':uid' => $user_id,
        ':sid' => $scholarship_id,
        ':title' => $title,
        ':details' => $details_full,
        ':doc' => $documentPath,
        ':status' => 'submitted',
        ':email' => $email
    ]);

    $_SESSION['success'] = 'Application submitted successfully!';
    header('Location: ../member/applications.php');
    exit;
}

// unsupported
$_SESSION['flash'] = 'Invalid request.';
header('Location: ../member/applications.php');
exit;