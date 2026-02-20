<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/ScreeningHelper.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

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
    $course = strtolower(trim($_POST['course'] ?? ''));
    $academic_year = date('Y'); // Or get from form if available

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

    // Duplicate application check (user, scholarship, academic year)
    $stmt = $pdo->prepare('SELECT id FROM applications WHERE user_id = :uid AND scholarship_id = :sid AND academic_year = :ay');
    $stmt->execute([':uid' => $user_id, ':sid' => $scholarship_id, ':ay' => $academic_year]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = 'Duplicate application detected: You have already applied for this scholarship in the current academic year.';
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
                // Accept GPA >= required_gpa (including exactly 3.5)
                if (strpos($req_lower, '>=') !== false && $user_gpa < $required_gpa) {
                    $validation_errors[] = "GPA requirement not met: $req (Your GPA: $gpa)";
                } elseif (strpos($req_lower, '>') !== false && $user_gpa <= $required_gpa) {
                    $validation_errors[] = "GPA requirement not met: $req (Your GPA: $gpa)";
                } elseif ($user_gpa > 5.0) {
                    $validation_errors[] = "GPA must not exceed 5.0.";
                }
            }
        }
        if (strpos($req_lower, 'course') !== false || strpos($req_lower, 'field of study') !== false) {
            // Example: "Course: Computer Science" or "Field of Study: STEM"
            if (preg_match('/(course|field of study)\s*:?\s*([a-zA-Z0-9 ]+)/', $req_lower, $matches)) {
                $required_course = trim($matches[2]);
                if (stripos($course, $required_course) === false) {
                    $validation_errors[] = "Course/Field requirement not met: $req (Your course: $course)";
                }
            }
        }
        if (strpos($req_lower, 'full-time') !== false || strpos($req_lower, 'full time') !== false) {
            if (!$full_time) {
                $validation_errors[] = "Must be enrolled full-time";
            }
        }
        if (strpos($req_lower, 'document') !== false || strpos($req_lower, 'upload') !== false) {
            if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $validation_errors[] = "Required document not uploaded.";
            }
        }
    }

    // Check deadline
    $stmt = $pdo->prepare('SELECT deadline FROM scholarships WHERE id = :id');
    $stmt->execute([':id' => $scholarship_id]);
    $scholarship_deadline = $stmt->fetchColumn();
    if ($scholarship_deadline && strtotime('now') > strtotime($scholarship_deadline)) {
        $validation_errors[] = "Application deadline has passed.";
    }

    if (!empty($validation_errors)) {
        $_SESSION['flash'] = 'Requirements not met: ' . implode(', ', $validation_errors);
        header('Location: ../member/apply_scholarship.php?scholarship_id=' . $scholarship_id);
        exit;
    }

    $documentPath = null;
    if (!empty($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        // Validate file upload
        $file_validation = validateFileUpload($_FILES['document']);
        if (!$file_validation['valid']) {
            $_SESSION['flash'] = 'File validation failed: ' . $file_validation['error'];
            header('Location: ../member/apply_scholarship.php?scholarship_id=' . $scholarship_id);
            exit;
        }
        
        $up = __DIR__ . '/../uploads';
        if (!is_dir($up)) mkdir($up, 0777, true);
        $name = sanitizeFilename(basename($_FILES['document']['name']));
        $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
        $target = $up . '/' . $safe;
        $file_size = $_FILES['document']['size'];
        $file_hash = hash_file('sha256', $_FILES['document']['tmp_name']);
        // Duplicate document check (same user, same hash, same filename, same size)
        $stmt = $pdo->prepare('SELECT id FROM documents WHERE user_id = :uid AND file_name = :fname AND file_size = :fsize AND file_hash = :fhash');
        $stmt->execute([
            ':uid' => $user_id,
            ':fname' => $name,
            ':fsize' => $file_size,
            ':fhash' => $file_hash
        ]);
        if ($stmt->fetch()) {
            $_SESSION['flash'] = 'Duplicate document detected. Please upload a different file.';
            header('Location: ../member/apply_scholarship.php?scholarship_id=' . $scholarship_id);
            exit;
        }
        if (move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
            $documentPath = 'uploads/' . $safe;
            // Save document record
            $docStmt = $pdo->prepare('INSERT INTO documents (user_id, file_name, file_path, file_size, file_hash, uploaded_at) VALUES (:uid, :fname, :fpath, :fsize, :fhash, NOW())');
            $docStmt->execute([
                ':uid' => $user_id,
                ':fname' => $name,
                ':fpath' => $documentPath,
                ':fsize' => $file_size,
                ':fhash' => $file_hash
            ]);
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

    $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, academic_year, title, details, document, status, email) VALUES (:uid, :sid, :ay, :title, :details, :doc, :status, :email)');
    $stmt->execute([
        ':uid' => $user_id,
        ':sid' => $scholarship_id,
        ':ay' => $academic_year,
        ':title' => $title,
        ':details' => $details_full,
        ':doc' => $documentPath,
        ':status' => 'draft',
        ':email' => $email
    ]);
    
    $application_id = $pdo->lastInsertId();
    
    // Link document to application if uploaded
    if ($documentPath) {
        $linkStmt = $pdo->prepare('UPDATE documents SET application_id = :appid WHERE file_path = :path');
        $linkStmt->execute([':appid' => $application_id, ':path' => $documentPath]);
    }
    
    // Perform intelligent application screening
    $screening_result = screenApplication($application_id, $user_id, $scholarship_id, $pdo);
    
    // Update application status based on screening
    $final_status = $screening_result['status'] ?? 'submitted';
    $updateStmt = $pdo->prepare('UPDATE applications SET status = :status WHERE id = :id');
    $updateStmt->execute([':status' => $final_status, ':id' => $application_id]);
    
    // Log audit trail
    logAuditTrail($pdo, $user_id, 'APPLICATION_SUBMITTED', 'applications', $application_id, 'Initial status: ' . $final_status);
    
    // Queue notification email
    $subject = 'Application Submitted - ' . $scholarship['title'];
    $body = "<p>Dear Applicant,</p><p>Your application for '<b>{$scholarship['title']}</b>' has been submitted successfully.</p>";
    if (isset($screening_result['issues'])) {
        $body .= "<p><b>Note:</b> Your application requires the following attention:</p><ul>";
        foreach ($screening_result['issues'] as $issue) {
            $body .= "<li>" . htmlspecialchars($issue) . "</li>";
        }
        $body .= "</ul>";
    }
    $body .= "<p>You will be notified once a reviewer begins evaluating your application.</p>";
    queueEmail($email, $subject, $body, $user_id);

    $_SESSION['success'] = 'Application submitted successfully! Status: ' . ucfirst(str_replace('_', ' ', $final_status));
    header('Location: ../member/applications.php');
    exit;
}

// Update application status
if ($action === 'update_status') {
    $application_id = (int)($_POST['application_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    
    if ($application_id && in_array($new_status, ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'waitlisted'])) {
        $stmt = $pdo->prepare('UPDATE applications SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $new_status, ':id' => $application_id]);
        logAuditTrail($pdo, $user_id, 'APPLICATION_STATUS_UPDATED', 'applications', $application_id, 'New status: ' . $new_status);
        $_SESSION['success'] = 'Application status updated.';
    }
    header('Location: ../member/applications.php');
    exit;
}

// unsupported
$_SESSION['flash'] = 'Invalid request.';
header('Location: ../member/applications.php');
exit;