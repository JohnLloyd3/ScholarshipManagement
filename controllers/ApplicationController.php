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
    $academic_year = date('Y'); // or override if provided later

    // collect posted fields for details
    $posted = $_POST;
    // ensure we don't include action or scholarship id
    unset($posted['action'], $posted['scholarship_id']);

    // simple required validations following spec
    $validation_errors = [];
    if (!$scholarship_id) {
        $validation_errors[] = 'Please select a scholarship.';
    }
    if (empty($posted['full_name'])) {
        $validation_errors[] = 'Full name is required.';
    }
    if (empty($posted['sex'])) {
        $validation_errors[] = 'Sex is required.';
    }
    if (empty($posted['dob'])) {
        $validation_errors[] = 'Date of birth is required.';
    }
    if (empty($posted['age'])) {
        $validation_errors[] = 'Age is required.';
    }
    if (empty($posted['civil_status'])) {
        $validation_errors[] = 'Civil status is required.';
    }
    if (empty($posted['mobile'])) {
        $validation_errors[] = 'Mobile number is required.';
    }
    if (empty($posted['email'])) {
        $validation_errors[] = 'Email address is required.';
    }
    if (empty($posted['home_address'])) {
        $validation_errors[] = 'Home address is required.';
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

    // Validate eligibility requirements generically (not enforcing GPA anymore)
    $stmt = $pdo->prepare('SELECT requirement FROM eligibility_requirements WHERE scholarship_id = :id');
    $stmt->execute([':id' => $scholarship_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // we will not enforce them in controller beyond file presence
    foreach ($requirements as $req) {
        if (stripos($req, 'document') !== false || stripos($req, 'upload') !== false) {
            // ensure at least one file uploaded when requirement mentions document
            if (empty($_FILES['documents']) || empty($_FILES['documents']['name'][0])) {
                $validation_errors[] = "Please upload required document(s): $req";
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
    $uploadedPaths = [];
    if (!empty($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
        $up = __DIR__ . '/../uploads';
        if (!is_dir($up)) mkdir($up, 0777, true);
        // normalize multiple file array
        $files = [];
        foreach ($_FILES['documents']['name'] as $i => $name) {
            $files[] = [
                'name' => $name,
                'type' => $_FILES['documents']['type'][$i] ?? '',
                'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                'error' => $_FILES['documents']['error'][$i],
                'size' => $_FILES['documents']['size'][$i],
            ];
        }
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            $file_validation = validateFileUpload($file);
            if (!$file_validation['valid']) {
                $_SESSION['flash'] = 'File validation failed: ' . $file_validation['error'];
                header('Location: ../member/apply_scholarship.php?scholarship_id=' . $scholarship_id);
                exit;
            }
            $name = sanitizeFilename(basename($file['name']));
            $safe = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
            $target = $up . '/' . $safe;
            $file_size = $file['size'];
            $file_hash = hash_file('sha256', $file['tmp_name']);
            // duplicate check
            $stmt = $pdo->prepare('SELECT id FROM documents WHERE user_id = :uid AND file_name = :fname AND file_size = :fsize AND file_hash = :fhash');
            $stmt->execute([
                ':uid' => $user_id,
                ':fname' => $name,
                ':fsize' => $file_size,
                ':fhash' => $file_hash
            ]);
            if ($stmt->fetch()) {
                continue; // skip duplicates silently
            }
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $pathRel = 'uploads/' . $safe;
                $uploadedPaths[] = $pathRel;
                // save document record (application_id will be linked later)
                $docStmt = $pdo->prepare('INSERT INTO documents (user_id, file_name, file_path, file_size, file_hash, uploaded_at) VALUES (:uid, :fname, :fpath, :fsize, :fhash, NOW())');
                $docStmt->execute([
                    ':uid' => $user_id,
                    ':fname' => $name,
                    ':fpath' => $pathRel,
                    ':fsize' => $file_size,
                    ':fhash' => $file_hash
                ]);
            }
        }
        if (!empty($uploadedPaths)) {
            $documentPath = $uploadedPaths[0];
        }
    }

    // generate title from applicant name if available
    $title = $posted['full_name'] ?? ($scholarship['title'] . ' Application');

    // details as JSON for record
    $details_full = json_encode($posted, JSON_UNESCAPED_UNICODE);

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
    
    // Link any uploaded documents to application
    if (!empty($uploadedPaths)) {
        $linkStmt = $pdo->prepare('UPDATE documents SET application_id = :appid WHERE file_path = :path');
        foreach ($uploadedPaths as $path) {
            $linkStmt->execute([':appid' => $application_id, ':path' => $path]);
        }
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