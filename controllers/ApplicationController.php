<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/ScreeningHelper.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash'] = 'Please log in to submit an application.';
    header('Location: ../auth/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request. Please try again.';
    header('Location: ../students/apply_scholarship.php');
    exit;
}

if ($action === 'create') {
    $scholarship_id = (int)($_POST['scholarship_id'] ?? 0);

    // collect posted fields for details
    $posted = $_POST;
    // ensure we don't include action or scholarship id
    unset($posted['action'], $posted['scholarship_id']);

    // Build full_name server-side if JS didn't combine it
    if (empty($posted['full_name'])) {
        $fn = trim(($posted['first_name'] ?? '') . ' ' . ($posted['middle_name'] ?? '') . ' ' . ($posted['last_name'] ?? ''));
        $posted['full_name'] = preg_replace('/\s+/', ' ', $fn);
        $_POST['full_name'] = $posted['full_name'];
    }

    // Build home_address server-side if JS didn't combine it
    if (empty($posted['home_address'])) {
        $parts = array_filter([
            $posted['street'] ?? '',
            $posted['barangay'] ?? '',
            $posted['city'] ?? '',
            $posted['province'] ?? ''
        ]);
        $posted['home_address'] = implode(', ', $parts);
        $_POST['home_address'] = $posted['home_address'];
    }

    // determine if user is saving draft
    $is_draft = !empty($_POST['save_draft']);

    // simple required validations following spec (skip strict validation for drafts)
    $validation_errors = [];
    if (!$scholarship_id) {
        $validation_errors[] = 'Please select a scholarship.';
    }
    if (!$is_draft) {
        if (empty(trim($posted['full_name'] ?? ''))) {
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
    }
    if (!empty($validation_errors)) {
        $_SESSION['flash'] = implode(' ', $validation_errors);
        header('Location: ../students/apply_scholarship.php' . ($scholarship_id ? '?scholarship_id=' . $scholarship_id : ''));
        exit;
    }

    // Check if scholarship exists and is open
    $stmt = $pdo->prepare('SELECT * FROM scholarships WHERE id = :id AND status = "open"');
    $stmt->execute([':id' => $scholarship_id]);
    $scholarship = $stmt->fetch();

    if (!$scholarship) {
        $_SESSION['flash'] = 'Scholarship not found or is closed.';
        header('Location: ../students/apply_scholarship.php');
        exit;
    }

    // Duplicate application check (user, scholarship) - only prevent duplicate on final submission
    if (!$is_draft) {
        $stmt = $pdo->prepare('SELECT id FROM applications WHERE user_id = :uid AND scholarship_id = :sid AND status != "draft"');
        $stmt->execute([':uid' => $user_id, ':sid' => $scholarship_id]);
        if ($stmt->fetch()) {
            $_SESSION['flash'] = 'Duplicate application detected: You have already applied for this scholarship.';
            header('Location: ../students/apply_scholarship.php');
            exit;
        }
    }

    // Validate eligibility requirements generically (not enforcing GPA anymore)
    $stmt = $pdo->prepare('SELECT requirement FROM eligibility_requirements WHERE scholarship_id = :id');
    $stmt->execute([':id' => $scholarship_id]);
    $requirements = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Check required documents for non-draft submissions
    if (!$is_draft) {
        $stmt = $pdo->prepare('SELECT document_name FROM scholarship_documents WHERE scholarship_id = :id');
        $stmt->execute([':id' => $scholarship_id]);
        $required_docs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // Only enforce scholarship_documents if they exist AND files were provided via documents[] array
        if (!empty($required_docs) && !empty($_FILES['documents']['name'][0])) {
            $uploaded_count = 0;
            foreach ($_FILES['documents']['name'] as $name) {
                if (!empty($name)) $uploaded_count++;
            }
            if ($uploaded_count < count($required_docs)) {
                $missing_docs = implode(', ', $required_docs);
                $_SESSION['flash'] = "You uploaded $uploaded_count file(s) but " . count($required_docs) . " document(s) are required: $missing_docs";
                header('Location: ../students/apply_scholarship.php?scholarship_id=' . $scholarship_id);
                exit;
            }
        }
    }
    
    // we will not enforce them in controller beyond file presence
    foreach ($requirements as $req) {
        if (stripos($req, 'document') !== false || stripos($req, 'upload') !== false) {
            // ensure at least one file uploaded when requirement mentions document
            if (!$is_draft && (empty($_FILES['documents']) || empty($_FILES['documents']['name'][0]))) {
                $validation_errors[] = "Please upload required document(s): $req";
            }
        }
    }

    // Check deadline (only enforce on final submission)
    $stmt = $pdo->prepare('SELECT deadline FROM scholarships WHERE id = :id');
    $stmt->execute([':id' => $scholarship_id]);
    $scholarship_deadline = $stmt->fetchColumn();
    if (!$is_draft && $scholarship_deadline && strtotime('today') > strtotime($scholarship_deadline)) {
        $_SESSION['flash'] = 'Application deadline has passed.';
        header('Location: ../students/apply_scholarship.php?scholarship_id=' . $scholarship_id);
        exit;
    }

    if (!empty($validation_errors)) {
        $_SESSION['flash'] = 'Requirements not met: ' . implode(', ', $validation_errors);
        header('Location: ../students/apply_scholarship.php?scholarship_id=' . $scholarship_id);
        exit;
    }

    $documentPath = null;
    $uploadedPaths = [];
    $collectedUploads = [];

    // Collect individually named file fields from the form
    $namedFiles = ['cert_indigency', 'proof_enrollment', 'id_picture', 'report_card', 'birth_certificate', 'proof_income'];
    foreach ($namedFiles as $fieldName) {
        if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $_FILES['documents']['name'][]     = $_FILES[$fieldName]['name'];
            $_FILES['documents']['type'][]     = $_FILES[$fieldName]['type'];
            $_FILES['documents']['tmp_name'][] = $_FILES[$fieldName]['tmp_name'];
            $_FILES['documents']['error'][]    = $_FILES[$fieldName]['error'];
            $_FILES['documents']['size'][]     = $_FILES[$fieldName]['size'];
        }
    }

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
                header('Location: ../students/apply_scholarship.php?scholarship_id=' . $scholarship_id);
                exit;
            }
            $name = sanitizeFilename(basename($file['name']));
            $safe = bin2hex(random_bytes(8)) . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
            $target = $up . '/' . $safe;
            $file_size = $file['size'];

            // duplicate check by file hash
            $fileHash = md5_file($file['tmp_name']);
            try {
                $dupStmt = $pdo->prepare('SELECT id FROM documents WHERE application_id IS NOT NULL AND file_hash = :fhash');
                $dupStmt->execute([':fhash' => $fileHash]);
                if ($dupStmt->fetch()) {
                    continue; // skip duplicates silently
                }
            } catch (Exception $e) {
                // file_hash column may not exist yet, skip duplicate check
            }

            if (move_uploaded_file($file['tmp_name'], $target)) {
                // Set proper file permissions
                chmod($target, 0644);
                
                $pathRel = 'uploads/' . $safe;
                $uploadedPaths[] = $pathRel;
                $collectedUploads[] = [
                    'name' => $name,
                    'path' => $pathRel,
                    'size' => $file_size,
                    'mime' => $file['type'] ?? '',
                    'hash' => $fileHash,
                ];
            }
        }
        if (!empty($uploadedPaths)) {
            $documentPath = $uploadedPaths[0];
        }
    }

    // Extract GWA for gpa field, store all form data as details (JSON)
    $motivational_letter = json_encode($posted, JSON_UNESCAPED_UNICODE); // kept for compat

    // Use transaction to ensure application and document inserts are atomic
    try {
        $pdo->beginTransaction();

        $db_status = $is_draft ? 'draft' : 'submitted';
        $detailsJson = json_encode($posted, JSON_UNESCAPED_UNICODE);
        
        // Check if details column exists
        $hasDetailsColumn = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM applications LIKE 'details'");
            $hasDetailsColumn = (bool)$checkCol->fetch();
        } catch (Exception $e) {
            $hasDetailsColumn = false;
        }
        
        if ($hasDetailsColumn) {
            // Insert with details column
            if ($is_draft) {
                $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, status, details, created_at, updated_at) VALUES (:uid, :sid, :status, :details, NOW(), NOW())');
                $stmt->execute([
                    ':uid'     => $user_id,
                    ':sid'     => $scholarship_id,
                    ':status'  => $db_status,
                    ':details' => $detailsJson,
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, status, details, submitted_at, created_at, updated_at) VALUES (:uid, :sid, :status, :details, NOW(), NOW(), NOW())');
                $stmt->execute([
                    ':uid'     => $user_id,
                    ':sid'     => $scholarship_id,
                    ':status'  => $db_status,
                    ':details' => $detailsJson,
                ]);
            }
        } else {
            // Insert without details column (fallback)
            if ($is_draft) {
                $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, status, created_at, updated_at) VALUES (:uid, :sid, :status, NOW(), NOW())');
                $stmt->execute([
                    ':uid'     => $user_id,
                    ':sid'     => $scholarship_id,
                    ':status'  => $db_status,
                ]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO applications (user_id, scholarship_id, status, submitted_at, created_at, updated_at) VALUES (:uid, :sid, :status, NOW(), NOW(), NOW())');
                $stmt->execute([
                    ':uid'     => $user_id,
                    ':sid'     => $scholarship_id,
                    ':status'  => $db_status,
                ]);
            }
        }

        $application_id = $pdo->lastInsertId();

        // Insert any uploaded documents and link to application
        if (!empty($collectedUploads)) {
            $ins = $pdo->prepare('INSERT INTO documents (application_id, user_id, document_type, file_name, file_path, file_hash, file_size, mime_type, uploaded_at) VALUES (:appid, :uid, :doctype, :fname, :fpath, :fhash, :fsize, :mime, NOW())');
            foreach ($collectedUploads as $u) {
                $ins->execute([
                    ':appid'   => $application_id,
                    ':uid'     => $user_id,
                    ':doctype' => 'supporting',
                    ':fname'   => $u['name'],
                    ':fpath'   => $u['path'],
                    ':fhash'   => $u['hash'] ?? null,
                    ':fsize'   => $u['size'] ?? 0,
                    ':mime'    => $u['mime'] ?? '',
                ]);
            }
        }

        if (!$is_draft) {
            try {
                $screening_result = screenApplication($application_id, $user_id, $scholarship_id, $pdo);
                $final_status = $screening_result['status'] ?? 'submitted';
            } catch (Exception $e) {
                $final_status = 'submitted';
                $screening_result = [];
            }
            $updateStmt = $pdo->prepare('UPDATE applications SET status = :status WHERE id = :id');
            $updateStmt->execute([':status' => $final_status, ':id' => $application_id]);
        } else {
            $final_status = 'draft';
        }

        // Commit after successful inserts and screening
        $pdo->commit();

        // Log audit trail
        logAuditTrail($pdo, $user_id, 'APPLICATION_SUBMITTED', 'applications', $application_id, 'Initial status: ' . $final_status);

        // Queue notification email for final submissions only
        $applicant_email = $posted['email'] ?? '';
        if (!$is_draft) {
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
        } else {
            $_SESSION['success'] = 'Application saved as draft.';
        }
        header('Location: ../students/applications.php');
        exit;
    } catch (Exception $e) {
        // Rollback and cleanup uploaded files on failure
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($uploadedPaths as $p) {
            $full = __DIR__ . '/../' . $p;
            if (is_file($full)) @unlink($full);
        }
        error_log('Application submission failed: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $_SESSION['flash'] = 'Failed to submit application: ' . $e->getMessage();
        header('Location: ../students/apply_scholarship.php?scholarship_id=' . $scholarship_id);
        exit;
    }
}

// Update application status
if ($action === 'update_status') {
    $role = $_SESSION['user']['role'] ?? 'student';
    if (!in_array($role, ['admin', 'staff'], true)) {
        $_SESSION['flash'] = 'Access denied.';
        header('Location: ../students/applications.php');
        exit;
    }
    $application_id = (int)($_POST['application_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? '');
    
    if ($application_id && in_array($new_status, ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'waitlisted'])) {
        $stmt = $pdo->prepare('UPDATE applications SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $new_status, ':id' => $application_id]);
        logAuditTrail($pdo, $user_id, 'APPLICATION_STATUS_UPDATED', 'applications', $application_id, 'New status: ' . $new_status);
        $_SESSION['success'] = 'Application status updated.';
    }
    header('Location: ../students/applications.php');
    exit;
}

// unsupported
$_SESSION['flash'] = 'Invalid request.';
header('Location: ../students/applications.php');
exit;