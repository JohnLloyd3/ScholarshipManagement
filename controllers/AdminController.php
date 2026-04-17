<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/NotificationHelper.php';

startSecureSession();

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    $_SESSION['flash'] = 'Admin access only.';
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request. Please try again.';
    header('Location: ../admin/dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();

if ($action === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (!in_array($role, ['staff'])) {
        $_SESSION['flash'] = 'Invalid role for user creation.';
        header('Location: ../admin/users.php');
        exit;
    }
    if ($username === '' || $password === '' || $first === '' || $last === '' || $email === '' || $role === '') {
        $_SESSION['flash'] = 'Please complete all fields.';
        header('Location: ../admin/users.php');
        exit;
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = 'Username or email already taken.';
        header('Location: ../admin/users.php');
        exit;
    }
    $pwHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, role, active, email_verified, must_change_password, created_at) VALUES (:u, :p, :f, :l, :e, :r, 1, 1, 1, NOW())');
    $stmt->execute([':u'=>$username,':p'=>$pwHash,':f'=>$first,':l'=>$last,':e'=>$email,':r'=>$role]);
    $_SESSION['success'] = 'User created successfully.';
    header('Location: ../admin/users.php');
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
        $appStmt = $pdo->prepare('SELECT a.user_id, a.email, a.title, a.scholarship_id, s.title AS scholarship_title FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id WHERE a.id = :id');
        $appStmt->execute([':id' => $id]);
        $appRow = $appStmt->fetch();

        $stmt = $pdo->prepare('UPDATE applications SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);

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
                notifyStudent($pdo, $userId, 'Application ' . ucfirst($status), 'Your application for "' . $scholarshipTitle . '" has been ' . $status . '.', $status === 'approved' ? 'success' : 'warning');
            }
    }
    header('Location: ../admin/applications.php');
    exit;
}

if ($action === 'update_application') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $reviewComments = trim($_POST['review_comments'] ?? '');

    if ($id && $title !== '' && in_array($status, ['draft','submitted','pending','under_review','approved','rejected','waitlisted'], true)) {
        $oldStmt = $pdo->prepare('SELECT a.status, a.user_id, a.email, a.scholarship_id, s.title AS scholarship_title FROM applications a LEFT JOIN scholarships s ON a.scholarship_id = s.id WHERE a.id = :id');
        $oldStmt->execute([':id' => $id]);
        $oldApp = $oldStmt->fetch();
        $scholarshipTitle = $oldApp['scholarship_title'] ?? null;

        $stmt = $pdo->prepare('UPDATE applications SET title = :t, details = :d, status = :s WHERE id = :id');
        $stmt->execute([':t'=>$title,':d'=>$details,':s'=>$status,':id'=>$id]);

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
                $msg = 'Your application for "'.$schTitle.'" has been '.$status.'.';
                if ($reviewComments !== '') $msg .= "\n\nComment: ".$reviewComments;
                notifyStudent($pdo, $userId, 'Application ' . ucfirst($status), $msg, $status === 'approved' ? 'success' : 'warning');
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
    if ($user_id && in_array($role, ['admin', 'student', 'staff']) && $user_id != $_SESSION['user_id']) {
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
            $stmt->execute([':t'=>$title,':d'=>$description,':o'=>$organization,':c'=>$category,':s'=>$status,':gpa'=>$gpa,':inc'=>$income,':max'=>$max_scholars,':dl'=>$deadline,':ac'=>$auto_close]);
            $scholarship_id = $pdo->lastInsertId();
            if (is_array($requirements)) {
                $reqStmt = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)');
                foreach ($requirements as $req) { $req = trim($req); if ($req) $reqStmt->execute([':sid'=>$scholarship_id,':req'=>$req]); }
            }
            if (is_array($documents)) {
                $docStmt = $pdo->prepare('INSERT INTO scholarship_documents (scholarship_id, document_name) VALUES (:sid, :doc)');
                foreach ($documents as $doc) { $doc = trim($doc); if ($doc) $docStmt->execute([':sid'=>$scholarship_id,':doc'=>$doc]); }
            }
            $_SESSION['success'] = 'Scholarship created successfully.';
        } catch (PDOException $e) {
            $_SESSION['flash'] = strpos($e->getMessage(), 'unique_scholarship') !== false ? 'A scholarship with this title and organization already exists.' : 'Failed to create scholarship.';
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

    if ($id && $title) {
        try {
            $stmt = $pdo->prepare('UPDATE scholarships SET title=:t, description=:d, organization=:o, category=:c, status=:s, gpa_requirement=:gpa, income_requirement=:inc, max_scholars=:max, deadline=:dl, auto_close=:ac WHERE id=:id');
            $stmt->execute([':t'=>$title,':d'=>$description,':o'=>$organization,':c'=>$category,':s'=>$status,':gpa'=>$gpa,':inc'=>$income,':max'=>$max_scholars,':dl'=>$deadline,':ac'=>$auto_close,':id'=>$id]);

            $pdo->prepare('DELETE FROM eligibility_requirements WHERE scholarship_id = :id')->execute([':id' => $id]);
            if (is_array($requirements)) {
                $reqStmt = $pdo->prepare('INSERT INTO eligibility_requirements (scholarship_id, requirement) VALUES (:sid, :req)');
                foreach ($requirements as $req) { $req = trim($req); if ($req) $reqStmt->execute([':sid'=>$id,':req'=>$req]); }
            }
            $pdo->prepare('DELETE FROM scholarship_documents WHERE scholarship_id = :id')->execute([':id' => $id]);
            if (is_array($documents)) {
                $docStmt = $pdo->prepare('INSERT INTO scholarship_documents (scholarship_id, document_name) VALUES (:sid, :doc)');
                foreach ($documents as $doc) { $doc = trim($doc); if ($doc) $docStmt->execute([':sid'=>$id,':doc'=>$doc]); }
            }
            $_SESSION['success'] = 'Scholarship updated.';
        } catch (PDOException $e) {
            $_SESSION['flash'] = strpos($e->getMessage(), 'unique_scholarship') !== false ? 'A scholarship with this title and organization already exists.' : 'Failed to update scholarship.';
        }
    } else {
        $_SESSION['flash'] = 'Invalid scholarship update.';
    }
    header('Location: ../admin/scholarships.php');
    exit;
}

$_SESSION['flash'] = 'Unknown action.';
header('Location: ../admin/dashboard.php');
exit;
