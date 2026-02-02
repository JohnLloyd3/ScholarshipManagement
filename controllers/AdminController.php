<?php
session_start();
require_once __DIR__ . '/../config/db.php';

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
    if ($id && in_array($status, ['submitted','pending','approved','rejected'], true)) {
        $stmt = $pdo->prepare('UPDATE applications SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);

        // If there is a review record for this application, keep latest one in sync
        $rst = $pdo->prepare('SELECT id FROM reviews WHERE application_id = :aid ORDER BY created_at DESC LIMIT 1');
        $rst->execute([':aid' => $id]);
        $r = $rst->fetch();
        if ($r) {
            $pdo->prepare('UPDATE reviews SET status = :s WHERE id = :id')->execute([':s' => $status, ':id' => $r['id']]);
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
    $reviewerId = ($reviewer !== '' && ctype_digit($reviewer)) ? (int)$reviewer : null;

    if ($id && $title !== '' && in_array($status, ['submitted','pending','approved','rejected'], true)) {
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
    $status = trim($_POST['status'] ?? 'open');
    $requirements = $_POST['requirements'] ?? [];
    
    if ($title) {
        try {
            $stmt = $pdo->prepare('INSERT INTO scholarships (title, description, organization, status) VALUES (:t, :d, :o, :s)');
            $stmt->execute([':t' => $title, ':d' => $description, ':o' => $organization, ':s' => $status]);
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
    $status = trim($_POST['status'] ?? 'open');
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
            } else {
                $_SESSION['flash'] = 'Failed to update scholarship.';
            }
        }
    }
    header('Location: ../admin/scholarships.php');
    exit;
}

if ($action === 'delete_scholarship') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare('DELETE FROM scholarships WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $_SESSION['success'] = 'Scholarship deleted.';
    }
    header('Location: ../admin/scholarships.php');
    exit;
}

// Unknown action
$_SESSION['flash'] = 'Unknown action.';
header('Location: ../admin/dashboard.php');
exit;