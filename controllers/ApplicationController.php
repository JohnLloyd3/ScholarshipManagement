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

    // Duplicate application check (user, scholarship)
    $stmt = $pdo->prepare('SELECT id FROM applications WHERE user_id = :uid AND scholarship_id = :sid');
    $stmt->execute([':uid' => $user_id, ':sid' => $scholarship_id]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = 'Duplicate application detected: You have already applied for this scholarship.';
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
    $collectedUploads = [];
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
        $collectedUploads = [];
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

            // duplicate check (without file hash)
            $dupStmt = $pdo->prepare('SELECT id FROM documents WHERE user_id = :uid AND file_name = :fname AND file_size = :fsize');
            $dupStmt->execute([
                ':uid' => $user_id,
                ':fname' => $name,
                ':fsize' => $file_size
            ]);
            if ($dupStmt->fetch()) {
                continue; // skip duplicates silently
            }

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $pathRel = 'uploads/' . $safe;
                $uploadedPaths[] = $pathRel;
                $collectedUploads[] = [
                    'name' => $name,
                    'path' => $pathRel,
                    'size' => $file_size,
                    'mime' => $file['type'] ?? ''
                ];
            }
        }
        if (!empty($uploadedPaths)) {
            $documentPath = $uploadedPaths[0];
        }
    }

    // Extract GWA for gpa field, store all form data as motivational_letter (JSON)
    $gwa = floatval($posted['gwa'] ?? 0);
    $motivational_letter = json_encode($posted, JSON_UNESCAPED_UNICODE);

    // Use transaction to ensure application and document inserts are atomic
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, gpa, motivational_letter, status, submitted_at) VALUES (:uid, :sid, :gpa, :motiv, :status, NOW())');
        $stmt->execute([
            ':uid' => $user_id,
            ':sid' => $scholarship_id,
            ':gpa' => $gwa,
            ':motiv' => $motivational_letter,
            ':status' => 'submitted'
        ]);

        $application_id = $pdo->lastInsertId();

        // Insert any uploaded documents and link to application
        if (!empty($collectedUploads)) {
            $ins = $pdo->prepare('INSERT INTO documents (application_id, user_id, document_type, file_name, file_path, file_size, mime_type, verification_status, uploaded_at) VALUES (:appid, :uid, :doctype, :fname, :fpath, :fsize, :mime, :vstatus, NOW())');
            foreach ($collectedUploads as $u) {
                $ins->execute([
                    ':appid' => $application_id,
                    ':uid' => $user_id,
                    ':doctype' => 'supporting',
                    ':fname' => $u['name'],
                    ':fpath' => $u['path'],
                    ':fsize' => $u['size'],
                    ':mime' => $u['mime'],
                    ':vstatus' => 'pending'
                ]);
            }
        }

        // Perform intelligent application screening (may adjust status)
        $screening_result = screenApplication($application_id, $user_id, $scholarship_id, $pdo);
        $final_status = $screening_result['status'] ?? 'submitted';

        $updateStmt = $pdo->prepare('UPDATE applications SET status = :status WHERE id = :id');
        $updateStmt->execute([':status' => $final_status, ':id' => $application_id]);

        // Commit after successful inserts and screening
        $pdo->commit();

        // Log audit trail
        logAuditTrail($pdo, $user_id, 'APPLICATION_SUBMITTED', 'applications', $application_id, 'Initial status: ' . $final_status);

        // Queue notification email
        $applicant_email = $posted['email'] ?? '';
        $subject = 'Application Submitted - ' . $scholarship['title'];
        $body = "<p>Dear Applicant,</p><p>Your application for '<b>{$scholarship['title']}</b>' has been submitted successfully.</p>";
        if (isset($screening_result['issues'])) {
            $body .= "<p><b>Note:</b> Your application requires the following attention:</p><ul>";
            foreach ($screening_result['issues'] as $issue) {
                $body .= "<li>" . htmlspecialchars($issue) . "</li>";
            }
            $body .= "</ul>";
        }
        $body .= "<p>You will be notified once evaluation begins.</p>";
        queueEmail($applicant_email, $subject, $body, $user_id);

        $_SESSION['success'] = 'Application submitted successfully! Status: ' . ucfirst(str_replace('_', ' ', $final_status));
        header('Location: ../member/applications.php');
        exit;
    } catch (Exception $e) {
        // Rollback and cleanup uploaded files on failure
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($uploadedPaths as $p) {
            $full = __DIR__ . '/../' . $p;
            if (is_file($full)) @unlink($full);
        }
        error_log('Application submission failed: ' . $e->getMessage());
        $_SESSION['flash'] = 'Failed to submit application. Please try again.';
        header('Location: ../member/apply_scholarship.php?scholarship_id=' . $scholarship_id);
        exit;
    }
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