if ($action === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Only allow staff or reviewer
    if (!in_array($role, ['staff', 'reviewer'])) {
        $_SESSION['flash'] = 'Invalid role for user creation.';
        header('Location: ../admin/users.php');
        exit;
    }
    if ($username === '' || $password === '' || $first === '' || $last === '' || $email === '' || $role === '') {
        $_SESSION['flash'] = 'Please complete all fields.';
        header('Location: ../admin/users.php');
        exit;
    }
    // Check username or email exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = 'Username or email already taken.';
        header('Location: ../admin/users.php');
        exit;
    }
    $pwHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, role, active, email_verified, created_at) VALUES (:u, :p, :f, :l, :e, :r, 1, 1, NOW())');
    $stmt->execute([
        ':u' => $username,
        ':p' => $pwHash,
        ':f' => $first,
        ':l' => $last,
        ':e' => $email,
        ':r' => $role
    ]);
    $_SESSION['success'] = 'User created successfully.';
    header('Location: ../admin/users.php');
    exit;
}
<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    $_SESSION['flash'] = 'Admin access only.';
    header('Location: ../auth/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();

if ($action === 'assign') {
    $id = (int)($_POST['id'] ?? 0);
    $reviewer = (int)($_POST['reviewer_id'] ?? 0);
    if ($id && $reviewer) {
        $stmt = $pdo->prepare('UPDATE applications SET reviewer_id = :r, status = "pending" WHERE id = :id');
        $stmt->execute([':r' => $reviewer, ':id' => $id]);

        // Create a review row for the assigned reviewer
        $rstmt = $pdo->prepare('INSERT INTO reviews (application_id, reviewer_id, status) VALUES (:app, :rev, :st)');
        $rstmt->execute([':app' => $id, ':rev' => $reviewer, ':st' => 'pending']);

        $_SESSION['success'] = 'Reviewer assigned.';
    }
    header('Location: ../admin/applications.php');
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare('DELETE FROM applications WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = 'Application deleted.';
    }
    header('Location: ../admin/applications.php');
    exit;
}

if ($action === 'set_application_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if ($id && in_array($status, ['draft','submitted','pending','under_review','approved','rejected','waitlisted'], true)) {
        // Fetch application for notification (before update)
        $appStmt = $pdo->prepare('SELECT a.user_id, a.email, a.title, a.scholarship_id, s.title AS scholarship_title FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id WHERE a.id = :id');
        $appStmt->execute([':id' => $id]);
        $appRow = $appStmt->fetch();

        $stmt = $pdo->prepare('UPDATE applications SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);

        // If there is a review record for this application, keep latest one in sync
        $rst = $pdo->prepare('SELECT id FROM reviews WHERE application_id = :aid ORDER BY created_at DESC LIMIT 1');
        $rst->execute([':aid' => $id]);
        $r = $rst->fetch();
        if ($r) {
            $pdo->prepare('UPDATE reviews SET status = :s WHERE id = :id')->execute([':s' => $status, ':id' => $r['id']]);
        }

        // 3.6 Notify applicant when approved or rejected
        if ($appRow && in_array($status, ['approved', 'rejected'], true)) {
            $userId = $appRow['user_id'];
            $email = $appRow['email'];
            $scholarshipTitle = $appRow['scholarship_title'] ?? $appRow['title'];
            $applicantName = 'Applicant';
            if ($userId) {
                $u = $pdo->prepare('SELECT email, first_name, last_name FROM users WHERE id = :id');
                $u->execute([':id' => $userId]);
                $uRow = $u->fetch();
                if ($uRow) {
                    $email = $email ?: $uRow['email'];
                    $applicantName = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')) ?: 'Applicant';
                }
                $ins = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, :type)');
                $ins->execute([
                    ':uid' => $userId,
                    ':title' => 'Application ' . ucfirst($status),
                    ':msg' => 'Your application for "' . $scholarshipTitle . '" has been ' . $status . '.',
                    ':type' => $status === 'approved' ? 'success' : 'warning'
                ]);
            }
            if ($email) {
                $subject = 'Application ' . ucfirst($status);
                $body = "<p>Dear $applicantName,</p><p>Your application for '<b>$scholarshipTitle</b>' has been <b>$status</b>.</p>";
                queueEmail($email, $subject, $body, $userId);
            }
        }

        $_SESSION['success'] = 'Application status updated.';
    }
    header('Location: ../admin/applications.php');
    exit;
}

if ($action === 'update_application') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $reviewer = trim($_POST['reviewer_id'] ?? '');
    $reviewComments = trim($_POST['review_comments'] ?? '');
    $reviewerId = ($reviewer !== '' && ctype_digit($reviewer)) ? (int)$reviewer : null;

    if ($id && $title !== '' && in_array($status, ['draft','submitted','pending','under_review','approved','rejected','waitlisted'], true)) {
        // Fetch current application for notification (before update)
        $oldStmt = $pdo->prepare('SELECT a.status, a.user_id, a.email, a.scholarship_id, s.title AS scholarship_title FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id WHERE a.id = :id');
        $oldStmt->execute([':id' => $id]);
        $oldApp = $oldStmt->fetch();
        $scholarshipTitle = $oldApp['scholarship_title'] ?? null;

        $stmt = $pdo->prepare('UPDATE applications SET title = :t, details = :d, status = :s, reviewer_id = :rid WHERE id = :id');
        $stmt->execute([
            ':t' => $title,
            ':d' => $details,
            ':s' => $status,
            ':rid' => $reviewerId,
            ':id' => $id
        ]);

        // Ensure a review row exists when reviewer is assigned
        if ($reviewerId) {
            $rst = $pdo->prepare('SELECT id FROM reviews WHERE application_id = :aid AND reviewer_id = :rid ORDER BY created_at DESC LIMIT 1');
            $rst->execute([':aid' => $id, ':rid' => $reviewerId]);
            if (!$rst->fetch()) {
                $pdo->prepare('INSERT INTO reviews (application_id, reviewer_id, status) VALUES (:app, :rev, :st)')
                    ->execute([':app' => $id, ':rev' => $reviewerId, ':st' => ($status === 'submitted' ? 'pending' : $status)]);
            }
        }

        // Keep latest review status in sync if exists
        $rst = $pdo->prepare('SELECT id FROM reviews WHERE application_id = :aid ORDER BY created_at DESC LIMIT 1');
        $rst->execute([':aid' => $id]);
        $r = $rst->fetch();
        if ($r) {
            $pdo->prepare('UPDATE reviews SET status = :s WHERE id = :id')->execute([':s' => $status, ':id' => $r['id']]);
        }

        // 3.6 Notify applicant when status changed to approved or rejected
        if ($oldApp && in_array($status, ['approved', 'rejected'], true)) {
            $userId = $oldApp['user_id'];
            $email = $oldApp['email'];
            $schTitle = $scholarshipTitle ?: $title;
            $applicantName = 'Applicant';
            if ($userId) {
                $u = $pdo->prepare('SELECT email, first_name, last_name FROM users WHERE id = :id');
                $u->execute([':id' => $userId]);
                $uRow = $u->fetch();
                if ($uRow) {
                    $email = $email ?: $uRow['email'];
                    $applicantName = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')) ?: 'Applicant';
                }
                $ins = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, :type)');
                $msg = 'Your application for "' . $schTitle . '" has been ' . $status . '.';
                if ($reviewComments !== '') {
                    $msg .= "\n\nComment: " . $reviewComments;
                }
                $ins->execute([
                    ':uid' => $userId,
                    ':title' => 'Application ' . ucfirst($status),
                    ':msg' => $msg,
                    ':type' => $status === 'approved' ? 'success' : 'warning'
                ]);
            }
            if ($email) {
                $subject = 'Application ' . ucfirst($status);
                $body = "<p>Dear $applicantName,</p><p>Your application for '<b>$schTitle</b>' has been <b>$status</b>.</p>";
                if ($reviewComments !== '') {
                    $body .= "<p><b>Reviewer comment:</b> " . nl2br(htmlspecialchars($reviewComments)) . "</p>";
                }
                queueEmail($email, $subject, $body, $userId);
            }
        }

        $_SESSION['success'] = 'Application updated.';
    } else {
        $_SESSION['flash'] = 'Invalid application update.';
    }
    header('Location: ../admin/applications.php?edit=' . $id);
    exit;
}

if ($action === 'activate_user' || $action === 'deactivate_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $active = $action === 'activate_user' ? 1 : 0;
    
    if ($user_id && $user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare('UPDATE users SET active = :a WHERE id = :id');
        $stmt->execute([':a' => $active, ':id' => $user_id]);
        $_SESSION['success'] = $active ? 'User activated.' : 'User deactivated.';
    }
    header('Location: ../admin/users.php');
    exit;
}

if ($action === 'update_role') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $role = trim($_POST['role'] ?? '');
    
    if ($user_id && in_array($role, ['admin', 'reviewer', 'student', 'staff']) && $user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare('UPDATE users SET role = :r WHERE id = :id');
        $stmt->execute([':r' => $role, ':id' => $user_id]);
        $_SESSION['success'] = 'User role updated.';
    }
    header('Location: ../admin/users.php');
    exit;
}

if ($action === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id && $user_id != $_SESSION['user_id']) {
        // Deleting a user will also delete related reviews (via FK) and set application.user_id NULL (via FK)
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $user_id]);
        $_SESSION['success'] = 'User deleted.';
    }
    header('Location: ../admin/users.php');
    exit;
}

if ($action === 'create_scholarship') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'open');
    $requirements = $_POST['requirements'] ?? [];
    $documents = $_POST['documents'] ?? [];
    $gpa = $_POST['gpa_requirement'] ?? null;
    $income = $_POST['income_requirement'] ?? null;
    $max_scholars = $_POST['max_scholars'] ?? null;
    $deadline = $_POST['deadline'] ?? null;
    $auto_close = $_POST['auto_close'] ?? 0;

    if ($title) {
        try {
            $stmt = $pdo->prepare('INSERT INTO scholarships (title, description, organization, category, status, gpa_requirement, income_requirement, max_scholars, deadline, auto_close) VALUES (:t, :d, :o, :c, :s, :gpa, :inc, :max, :dl, :ac)');
            $stmt->execute([
                ':t' => $title,
                ':d' => $description,
                ':o' => $organization,
                ':c' => $category,
                ':s' => $status,
                ':gpa' => $gpa,
                ':inc' => $income,
                ':max' => $max_scholars,
                ':dl' => $deadline,
                ':ac' => $auto_close
            ]);
            $scholarship_id = $pdo->lastInsertId();
            // Add requirements
            if (is_array($requirements)) {
                $reqStmt = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)');
                foreach ($requirements as $req) {
                    $req = trim($req);
                    if ($req) {
                        $reqStmt->execute([':sid' => $scholarship_id, ':req' => $req]);
                    }
                }
            }
            // Add required documents
            if (is_array($documents)) {
                $docStmt = $pdo->prepare('INSERT INTO scholarship_documents (scholarship_id, document_name) VALUES (:sid, :doc)');
                foreach ($documents as $doc) {
                    $doc = trim($doc);
                    if ($doc) {
                        $docStmt->execute([':sid' => $scholarship_id, ':doc' => $doc]);
                    }
                }
            }
            $_SESSION['success'] = 'Scholarship created successfully.';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_scholarship') !== false) {
                $_SESSION['flash'] = 'A scholarship with this title and organization already exists.';
            } else {
                $_SESSION['flash'] = 'Failed to create scholarship.';
            }
        }
    }
    header('Location: ../admin/scholarships.php');
    exit;
}

if ($action === 'update_scholarship') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'open');
    $requirements = $_POST['requirements'] ?? [];
    $documents = $_POST['documents'] ?? [];
    $gpa = $_POST['gpa_requirement'] ?? null;
    $income = $_POST['income_requirement'] ?? null;
    $max_scholars = $_POST['max_scholars'] ?? null;
    $deadline = $_POST['deadline'] ?? null;
    $auto_close = $_POST['auto_close'] ?? 0;
    $requirements = $_POST['requirements'] ?? [];
    
    if ($id && $title) {
        try {
            $stmt = $pdo->prepare('UPDATE scholarships SET title = :t, description = :d, organization = :o, status = :s WHERE id = :id');
            $stmt->execute([':t' => $title, ':d' => $description, ':o' => $organization, ':s' => $status, ':id' => $id]);
            
            // Delete old requirements and add new ones
            $pdo->prepare('DELETE FROM eligibility_requirements WHERE scholarship_id = :id')->execute([':id' => $id]);
            
            if (is_array($requirements)) {
                $reqStmt = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)');
                foreach ($requirements as $req) {
                    $req = trim($req);
                    if ($req) {
                        $reqStmt->execute([':sid' => $id, ':req' => $req]);
                    }
                }
            }
            
            $_SESSION['success'] = 'Scholarship updated successfully.';
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_scholarship') !== false) {
                $_SESSION['flash'] = 'A scholarship with this title and organization already exists.';
            try {
                $stmt = $pdo->prepare('UPDATE scholarships SET title = :t, description = :d, organization = :o, category = :c, status = :s, gpa_requirement = :gpa, income_requirement = :inc, max_scholars = :max, deadline = :dl, auto_close = :ac WHERE id = :id');
                $stmt->execute([
                    ':t' => $title,
                    ':d' => $description,
                    ':o' => $organization,
                    ':c' => $category,
                    ':s' => $status,
                    ':gpa' => $gpa,
                    ':inc' => $income,
                    ':max' => $max_scholars,
                    ':dl' => $deadline,
                    ':ac' => $auto_close,
                    ':id' => $id
                ]);
                // Remove old requirements
                $pdo->prepare('DELETE FROM eligibility_requirements WHERE scholarship_id = :id')->execute([':id' => $id]);
                // Add new requirements
                if (is_array($requirements)) {
                    $reqStmt = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)');
                    foreach ($requirements as $req) {
                        $req = trim($req);
                        if ($req) {
                            $reqStmt->execute([':sid' => $id, ':req' => $req]);
                        }
                    }
                }
                // Remove old documents
                $pdo->prepare('DELETE FROM scholarship_documents WHERE scholarship_id = :id')->execute([':id' => $id]);
                // Add new required documents
                if (is_array($documents)) {
                    $docStmt = $pdo->prepare('INSERT INTO scholarship_documents (scholarship_id, document_name) VALUES (:sid, :doc)');
                    foreach ($documents as $doc) {
                        $doc = trim($doc);
                        if ($doc) {
                            $docStmt->execute([':sid' => $id, ':doc' => $doc]);
                        }
                    }
                }
                $_SESSION['success'] = 'Scholarship updated.';
            } catch (PDOException $e) {
                $_SESSION['flash'] = 'Failed to update scholarship.';
            }
        }
// Unknown action
$_SESSION['flash'] = 'Unknown action.';
header('Location: ../admin/dashboard.php');
exit;