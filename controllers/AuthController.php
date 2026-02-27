<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = null;
try {
    $pdo = getPDO();
} catch (Exception $e) {
    // Log the exception and continue with file-based fallback so users can still login/register locally
    error_log('[AuthController] DB connection error: ' . $e->getMessage());
    $_SESSION['flash'] = 'Database unavailable — using local fallback storage.';
    $pdo = null; // proceed, using file fallback
}

// File fallback helpers
function _users_file()
{
    $dataFile = __DIR__ . '/../data/users.json';
    if (!is_dir(dirname($dataFile))) mkdir(dirname($dataFile), 0777, true);
    if (!file_exists($dataFile)) file_put_contents($dataFile, json_encode([]));
    return $dataFile;
}

function _load_users()
{
    $f = _users_file();
    $contents = file_get_contents($f);
    $users = json_decode($contents, true) ?: [];
    return $users;
}

function _save_user_file($user)
{
    $f = _users_file();
    $users = _load_users();
    $users[] = $user;
    $fp = fopen($f, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($users, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    if ($fp) fclose($fp);
    return false;
}

function _redirect_dashboard_for_role($role)
{
    switch ($role) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            break;
        case 'staff':
            header("Location: ../staff/dashboard.php");
            break;
        default:
            header("Location: ../member/dashboard.php");
            break;
    }
    exit;
}

function _save_users($users)
{
    $f = _users_file();
    $fp = fopen($f, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        fwrite($fp, json_encode($users, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    if ($fp) fclose($fp);
    return false;
}

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $secretQuestion = trim($_POST['secret_question'] ?? '');
    $secretAnswer = trim($_POST['secret_answer'] ?? '');

    // Validate role - only allow student for public registration
    if ($role !== 'student') {
        $_SESSION['flash'] = "Only students can self-register. Staff/Reviewer accounts must be created by admin.";
        header("Location: ../auth/register.php");
        exit;
    }

    if ($username === '' || $password === '' || $first === '' || $last === '' || $email === '' || $role === '' || $secretQuestion === '' || $secretAnswer === '') {
        $_SESSION['flash'] = "Please complete required fields.";
        header("Location: ../auth/register.php");
        exit;
    }

    if ($pdo) {
        try {
            // Check username or email exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
            $stmt->execute([':u' => $username, ':e' => $email]);
            if ($stmt->fetch()) {
                $_SESSION['flash'] = 'Username or email already taken.';
                header("Location: ../auth/register.php");
                exit;
            }

            $pwHash = password_hash($password, PASSWORD_DEFAULT);
            $secretAnswerHash = password_hash($secretAnswer, PASSWORD_DEFAULT);
            // Email verification removed: accounts are created as verified immediately.
            $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, phone, address, role, email_verified, active, secret_question, secret_answer_hash) VALUES (:u, :p, :f, :l, :e, :ph, :a, :r, 1, 1, :sq, :sah)');
            $stmt->execute([
                ':u' => $username,
                ':p' => $pwHash,
                ':f' => $first,
                ':l' => $last,
                ':e' => $email,
                ':ph' => $phone,
                ':a' => $address,
                ':r' => $role,
                ':sq' => $secretQuestion,
                ':sah' => $secretAnswerHash
            ]);

            $id = $pdo->lastInsertId();

            // Log user in immediately after registration
            $_SESSION['user_id'] = $id;
            $_SESSION['user'] = [
                'username' => $username,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'role' => $role
            ];
            $_SESSION['success'] = 'Registration successful! Welcome, ' . $first . '.';
            _redirect_dashboard_for_role($role);

        } catch (PDOException $e) {
            _error_db($e);
            $_SESSION['flash'] = 'Failed to register. Try again.';
            header("Location: ../auth/register.php");
            exit;
        }
    } else {
        // Fallback: save to users.json
        $id = time() . rand(100,999);
        $newUser = [
            'id' => $id,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'created_at' => date('c'),
            'role' => $role,
            'secret_question' => $secretQuestion,
            'secret_answer_hash' => password_hash($secretAnswer, PASSWORD_DEFAULT)
        ];
        if (_save_user_file($newUser)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user'] = [
                'username' => $username,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'role' => $role
            ];
            $_SESSION['success'] = 'Registered locally (DB unavailable).';
            _redirect_dashboard_for_role($role);
        }

        $_SESSION['flash'] = 'Failed to save user locally. Try again.';
        header("Location: ../auth/register.php");
        exit;
    }

} elseif ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $_SESSION['flash'] = 'Provide username and password.';
        header("Location: ../auth/login.php");
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT id, username, password, first_name, last_name, email, role, active FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $found = $stmt->fetch();

            if (!$found || !password_verify($password, $found['password'])) {
                $_SESSION['flash'] = 'Invalid username or password.';
                header("Location: ../auth/login.php");
                exit;
            }

            // Check if account is active
            if (!$found['active']) {
                $_SESSION['flash'] = 'Your account has been deactivated. Please contact administrator.';
                header("Location: ../auth/login.php");
                exit;
            }

            // Direct login (no verify-login step, no email codes)
            $_SESSION['user_id'] = $found['id'];
            $_SESSION['user'] = [
                'username' => $found['username'],
                'first_name' => $found['first_name'],
                'last_name' => $found['last_name'],
                'email' => $found['email'],
                'role' => $found['role'] ?? 'student'
            ];
            $_SESSION['success'] = 'Welcome back, ' . ($found['first_name'] ?? $found['username']);
            _redirect_dashboard_for_role($found['role'] ?? 'student');

        } catch (PDOException $e) {
            _error_db($e);
            $_SESSION['flash'] = 'Login error. Try again later.';
            header("Location: ../auth/login.php");
            exit;
        }
    } else {
        // Fallback: check users.json for credentials
        $users = _load_users();
        $found = null;
        foreach ($users as $u) {
            if (strcasecmp($u['username'], $username) === 0) {
                $found = $u;
                break;
            }
        }

        if (!$found || !password_verify($password, $found['password'])) {
            $_SESSION['flash'] = 'Invalid username or password.';
            header("Location: ../auth/login.php");
            exit;
        }

        // Direct login (local fallback)
        $_SESSION['user_id'] = $found['id'];
        $_SESSION['user'] = [
            'username' => $found['username'],
            'first_name' => $found['first_name'],
            'last_name' => $found['last_name'],
            'email' => $found['email'],
            'role' => $found['role'] ?? 'student'
        ];
        $_SESSION['success'] = 'Welcome back, ' . ($found['first_name'] ?? $found['username']);
        _redirect_dashboard_for_role($found['role'] ?? 'student');
    }

} elseif ($action === 'request_password_reset') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (!$identifier) {
        $_SESSION['flash'] = 'Please provide your username or email.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }

    if ($pdo) {
        try {
            // Match identifier against username or email in a case-insensitive, trimmed manner
            // Use two distinct placeholders to avoid PDO "invalid parameter number" when emulated prepares are disabled
            $stmt = $pdo->prepare('SELECT id, email, secret_question FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:id1)) OR LOWER(TRIM(username)) = LOWER(TRIM(:id2)) LIMIT 1');
            $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $_SESSION['flash'] = 'Account not found.';
                header("Location: ../auth/forgot_password.php");
                exit;
            }

            if (!empty($user['secret_question'])) {
                // Use secret question flow
                $_SESSION['pending_reset'] = [
                    'user_id' => $user['id'],
                    'reset_type' => 'secret',
                    'secret_question' => $user['secret_question']
                ];
                $_SESSION['success'] = 'Please answer your secret question to reset your password.';
                header("Location: ../auth/reset_password.php");
                exit;
            }

            // Fallback: email-based reset when no secret question
            if (empty($user['email'])) {
                $_SESSION['flash'] = 'Account has no email on file. Please contact administrator.';
                header("Location: ../auth/forgot_password.php");
                exit;
            }

            $code = generateVerificationCode();
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $pdo->prepare('INSERT INTO email_verification_codes (user_id, email, code, type, expires_at) VALUES (:uid, :email, :code, :type, :exp)');
            $stmt->execute([
                ':uid' => $user['id'],
                ':email' => $user['email'],
                ':code' => $code,
                ':type' => 'password_reset',
                ':exp' => $expires
            ]);

            $subject = 'Password Reset Code';
            $message = getPasswordResetEmailTemplate($code);
            queueEmail($user['email'], $subject, $message, $user['id']);
            $_SESSION['pending_reset'] = [
                'user_id' => $user['id'],
                'reset_type' => 'email',
                'email' => $user['email']
            ];
            $_SESSION['success'] = 'A reset code was queued to your email. Please check your inbox.';
            header("Location: ../auth/reset_password.php");
            exit;
        } catch (PDOException $e) {
            error_log('[request_password_reset] ' . $e->getMessage());
            $_SESSION['flash'] = 'Error processing request. Try again.';
            header("Location: ../auth/forgot_password.php");
            exit;
        }
    }
    // Fallback (users.json)
    $users = _load_users();
    $found = null;
    foreach ($users as $u) {
        if (
            (!empty($u['email']) && strcasecmp($u['email'], $identifier) === 0) ||
            (!empty($u['username']) && strcasecmp($u['username'], $identifier) === 0)
        ) {
            $found = $u;
            break;
        }
    }
    if (!$found) {
        $_SESSION['flash'] = 'Account not found.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }
    if (empty($found['secret_question'])) {
        $_SESSION['flash'] = 'Account has no secret question. Database required for email reset.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }
    $_SESSION['pending_reset'] = [
        'user_id' => $found['id'],
        'reset_type' => 'secret',
        'secret_question' => $found['secret_question']
    ];
    $_SESSION['success'] = 'Please answer your secret question to reset your password.';
    header("Location: ../auth/reset_password.php");
    exit;
} elseif ($action === 'reset_password_by_code') {
    $code = trim($_POST['reset_code'] ?? '');
    $pending = $_SESSION['pending_reset'] ?? null;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$pending || ($pending['reset_type'] ?? '') !== 'email' || !$code || !$new_password) {
        $_SESSION['flash'] = 'Please complete all fields.';
        header("Location: ../auth/reset_password.php");
        exit;
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['flash'] = 'Passwords do not match.';
        header("Location: ../auth/reset_password.php");
        exit;
    }

    if (strlen($new_password) < 6) {
        $_SESSION['flash'] = 'Password must be at least 6 characters.';
        header("Location: ../auth/reset_password.php");
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT id, user_id FROM email_verification_codes WHERE user_id = :uid AND email = :email AND code = :code AND type = :type AND expires_at > NOW() AND used = 0 LIMIT 1');
            $stmt->execute([
                ':uid' => $pending['user_id'],
                ':email' => $pending['email'],
                ':code' => $code,
                ':type' => 'password_reset'
            ]);
            $row = $stmt->fetch();

            if (!$row) {
                $_SESSION['flash'] = 'Invalid or expired code. Please request a new reset.';
                header("Location: ../auth/forgot_password.php");
                exit;
            }

            $pwHash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
            $stmt->execute([':p' => $pwHash, ':id' => $pending['user_id']]);

            $pdo->prepare('UPDATE email_verification_codes SET used = 1 WHERE id = :id')->execute([':id' => $row['id']]);

            unset($_SESSION['pending_reset']);
            $_SESSION['success'] = 'Password reset successfully! Please login with your new password.';
            header("Location: ../auth/login.php");
            exit;
        } catch (PDOException $e) {
            error_log('[reset_password_by_code] ' . $e->getMessage());
            $_SESSION['flash'] = 'Password reset failed. Try again.';
            header("Location: ../auth/reset_password.php");
            exit;
        }
    }

    $_SESSION['flash'] = 'Database required for email reset.';
    header("Location: ../auth/forgot_password.php");
    exit;

} elseif ($action === 'reset_password') {
    $secretAnswer = trim($_POST['secret_answer'] ?? '');
    $pending = $_SESSION['pending_reset'] ?? null;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$pending || ($pending['reset_type'] ?? 'secret') !== 'secret' || !$secretAnswer || !$new_password) {
        $_SESSION['flash'] = 'Please complete all fields.';
        header("Location: ../auth/reset_password.php");
        exit;
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['flash'] = 'Passwords do not match.';
        header("Location: ../auth/reset_password.php");
        exit;
    }

    if (strlen($new_password) < 6) {
        $_SESSION['flash'] = 'Password must be at least 6 characters.';
        header("Location: ../auth/reset_password.php");
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT id, secret_answer_hash FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $pending['user_id']]);
            $user = $stmt->fetch();

            if (!$user || empty($user['secret_answer_hash']) || !password_verify($secretAnswer, $user['secret_answer_hash'])) {
                $_SESSION['flash'] = 'Incorrect secret answer.';
                header("Location: ../auth/reset_password.php");
                exit;
            }

            // Update password
            $pwHash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
            $stmt->execute([':p' => $pwHash, ':id' => $pending['user_id']]);

            unset($_SESSION['pending_reset']);
            $_SESSION['success'] = 'Password reset successfully! Please login with your new password.';
            header("Location: ../auth/login.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Password reset failed. Try again.';
            header("Location: ../auth/reset_password.php");
            exit;
        }
    }
    // Fallback (users.json) update password
    $users = _load_users();
    $idx = null;
    foreach ($users as $i => $u) {
        if ((string)($u['id'] ?? '') === (string)$pending['user_id']) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null || empty($users[$idx]['secret_answer_hash']) || !password_verify($secretAnswer, $users[$idx]['secret_answer_hash'])) {
        $_SESSION['flash'] = 'Incorrect secret answer.';
        header("Location: ../auth/reset_password.php");
        exit;
    }
    $users[$idx]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
    _save_users($users);
    unset($_SESSION['pending_reset']);
    $_SESSION['success'] = 'Password reset successfully! Please login with your new password.';
    header("Location: ../auth/login.php");
    exit;
} else {
    header("Location: ../auth/login.php");
    exit;
}

function _error_db($e)
{
    // For local development, you can log error messages to a file.
    // error_log($e->getMessage());
}

