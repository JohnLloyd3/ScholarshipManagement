<?php
/**
 * AUTH CONTROLLER
 * Role: All users
 * Purpose: Handles login, registration, logout, password reset, email verification
 * URL: /controllers/AuthController.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

startSecureSession();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    header("Location: ../auth/login.php");
    exit;
}

$action = $_POST['action'] ?? '';

// CSRF protection for all POST actions handled here.
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request. Please try again.';
    $redirect = '../auth/login.php';
    if ($action === 'register') $redirect = '../auth/register.php';
    if ($action === 'request_password_reset') $redirect = '../auth/forgot_password.php';
    if ($action === 'reset_password_by_code') $redirect = '../auth/reset_password.php';
    header("Location: $redirect");
    exit;
}

$pdo = null;
try {
    $pdo = getPDO();
} catch (Exception $e) {
    // Log the exception silently - system will use file-based fallback
    error_log('[AuthController] DB connection error: ' . $e->getMessage());
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
            header("Location: ../students/dashboard.php");
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

function _get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function _cleanup_login_attempts(PDO $pdo, int $seconds = 3600): void {
    try {
        $pdo->exec("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    } catch (PDOException $e) {
        error_log('[RateLimit] cleanup error: ' . $e->getMessage());
    }
}

function _count_recent_failures(PDO $pdo, string $field, string $value, int $window = 900): int {
    try {
        $allowed = ['username', 'ip_address'];
        if (!in_array($field, $allowed, true)) return 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE $field = :val AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)");
        $stmt->bindValue(':val', $value, PDO::PARAM_STR);
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[RateLimit] count error: ' . $e->getMessage());
        return 0;
    }
}

function _record_login_attempt(PDO $pdo, string $username, string $ip, int $success): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (:u, :ip, :s, NOW())");
        $stmt->execute([':u' => $username, ':ip' => $ip, ':s' => $success]);
    } catch (PDOException $e) {
        error_log('[RateLimit] record error: ' . $e->getMessage());
    }
}

function _earliest_failure_ts(PDO $pdo, string $field, string $value, int $window = 900): ?int {
    try {
        $allowed = ['username', 'ip_address'];
        if (!in_array($field, $allowed, true)) return null;
        $stmt = $pdo->prepare("SELECT MIN(created_at) FROM login_attempts WHERE $field = :val AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL :window SECOND)");
        $stmt->bindValue(':val', $value, PDO::PARAM_STR);
        $stmt->bindValue(':window', $window, PDO::PARAM_INT);
        $stmt->execute();
        $ts = $stmt->fetchColumn();
        return $ts ? (int)strtotime($ts) : null;
    } catch (PDOException $e) {
        error_log('[RateLimit] earliest_ts error: ' . $e->getMessage());
        return null;
    }
}

if ($action === 'register') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = trim($_POST['role'] ?? '');
    // Secret question/answer removed: rely on email-based resets instead

    // Validate role - only allow student for public registration
    if ($role !== 'student') {
        $_SESSION['flash'] = "Only students can self-register. Staff accounts must be created by admin.";
        header("Location: ../auth/register.php");
        exit;
    }

    if ($student_id === '' || $password === '' || $first === '' || $last === '' || $email === '' || $role === '') {
        $_SESSION['flash'] = "Please complete required fields.";
        header("Location: ../auth/register.php");
        exit;
    }

    if (strlen($password) < 8) {
        $_SESSION['flash'] = 'Password must be at least 8 characters.';
        header("Location: ../auth/register.php");
        exit;
    }

    if ($pdo) {
        try {
            // Check if student_id or email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE student_id = :sid OR email = :e LIMIT 1');
            $stmt->execute([':sid' => $student_id, ':e' => $email]);
            if ($stmt->fetch()) {
                $_SESSION['flash'] = 'Student ID or email already exists.';
                header("Location: ../auth/register.php");
                exit;
            }

            $pwHash = password_hash($password, PASSWORD_DEFAULT);
            // Insert account as INACTIVE - requires email verification
            // Use student_id as username for students
            $stmt = $pdo->prepare('INSERT INTO users (username, student_id, password, first_name, last_name, email, phone, address, role, email_verified, active) VALUES (:u, :sid, :p, :f, :l, :e, :ph, :a, :r, 0, 0)');
            $stmt->execute([
                ':u' => $student_id,  // Use student_id as username
                ':sid' => $student_id,
                ':p' => $pwHash,
                ':f' => $first,
                ':l' => $last,
                ':e' => $email,
                ':ph' => $phone,
                ':a' => $address,
                ':r' => $role
            ]);

            $id = $pdo->lastInsertId();

            // Generate verification token
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO activations (user_id, token) VALUES (:uid, :token)');
            $stmt->execute([':uid' => $id, ':token' => $token]);

            // Send verification email
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            // Get the base path (remove /controllers/AuthController.php)
            $scriptPath = dirname(dirname($_SERVER['SCRIPT_NAME']));
            $verifyLink = $protocol . "://" . $host . $scriptPath . "/auth/verify_email.php?token=" . $token;
            
            $subject = 'Verify Your ScholarHub Account';
            $message = "
                <html>
                <head><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;}
                .container{max-width:600px;margin:0 auto;padding:20px;background:#f9f9f9;}
                .header{background:#E53935;color:#fff;padding:20px;text-align:center;border-radius:8px 8px 0 0;}
                .content{background:#fff;padding:30px;border-radius:0 0 8px 8px;}
                .button{display:inline-block;padding:12px 30px;background:#E53935;color:#fff;text-decoration:none;border-radius:6px;margin:20px 0;}
                .footer{text-align:center;margin-top:20px;font-size:12px;color:#888;}
                </style></head>
                <body>
                <div class='container'>
                    <div class='header'><h1>Welcome to ScholarHub!</h1></div>
                    <div class='content'>
                        <h2>Hi {$first},</h2>
                        <p>Thank you for registering with ScholarHub. To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                        <p style='text-align:center;'><a href='{$verifyLink}' class='button'>Verify Email Address</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p style='word-break:break-all;color:#E53935;'>{$verifyLink}</p>
                        <p>This link will expire in 24 hours.</p>
                        <p>If you didn't create this account, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " ScholarHub. All rights reserved.</p>
                    </div>
                </div>
                </body>
                </html>
            ";

            try {
                queueEmail($email, $subject, $message, $id);
                $_SESSION['success'] = 'Registration successful! Please check your email (' . $email . ') to verify your account.';
            } catch (Exception $e) {
                $_SESSION['flash'] = 'Account created but failed to send verification email. Please contact support.';
            }

            header("Location: ../auth/login.php");
            exit;

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
            'username' => $student_id,  // Use student_id as username
            'student_id' => $student_id,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'created_at' => date('c'),
            'role' => $role,
            // secret question/answer intentionally omitted
        ];
        if (_save_user_file($newUser)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user'] = [
                'username' => $student_id,  // Use student_id as username
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
            _cleanup_login_attempts($pdo);
            $ip = _get_client_ip();
            $userFailures = _count_recent_failures($pdo, 'username', $username);
            $ipFailures   = _count_recent_failures($pdo, 'ip_address', $ip);

            if ($userFailures >= 5 || $ipFailures >= 5) {
                $userTs = _earliest_failure_ts($pdo, 'username', $username) ?? PHP_INT_MAX;
                $ipTs   = _earliest_failure_ts($pdo, 'ip_address', $ip) ?? PHP_INT_MAX;
                $ts = min($userTs, $ipTs);
                $secondsRemaining = ($ts + 900) - time();
                if ($secondsRemaining > 0) {
                    $minutesRemaining = (int)ceil($secondsRemaining / 60);
                    $_SESSION['flash'] = "Too many failed login attempts. Please try again in $minutesRemaining minute(s).";
                } else {
                    $_SESSION['flash'] = 'Too many failed login attempts. Please try again shortly.';
                }
                header("Location: ../auth/login.php");
                exit;
            }

            // Login with student_id (which is stored as username for students)
            $stmt = $pdo->prepare('SELECT id, username, student_id, password, first_name, last_name, email, role, active, email_verified FROM users WHERE student_id = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $found = $stmt->fetch();

            if (!$found) {
                _record_login_attempt($pdo, $username, $ip, 0);
                $_SESSION['flash'] = 'Student ID not found. Please check your Student ID.';
                header("Location: ../auth/login.php");
                exit;
            }

            if (!password_verify($password, $found['password'])) {
                _record_login_attempt($pdo, $username, $ip, 0);
                $_SESSION['flash'] = 'Incorrect password. Please try again.';
                header("Location: ../auth/login.php");
                exit;
            }

            // Check if email is verified
            if (!$found['email_verified']) {
                $_SESSION['flash'] = 'Please verify your email address. Check your inbox for the verification link.';
                header("Location: ../auth/login.php");
                exit;
            }

            // Check if account is active
            if (!$found['active']) {
                $_SESSION['flash'] = 'Your account has been deactivated. Please contact administrator.';
                header("Location: ../auth/login.php");
                exit;
            }

            _record_login_attempt($pdo, $username, $ip, 1);

            // Direct login (no verify-login step, no email codes)
            rotateSession();
            $_SESSION['user_id'] = $found['id'];
            $_SESSION['user'] = [
                'username' => $found['username'],
                'first_name' => $found['first_name'],
                'last_name' => $found['last_name'],
                'email' => $found['email'],
                'role' => $found['role'] ?? 'student'
            ];

            // Force password change if flagged (safe � column may not exist yet)
            try {
                $mustChange = false;
                $colCheck = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'must_change_password'")->fetch();
                if ($colCheck) {
                    $mcStmt = $pdo->prepare('SELECT must_change_password FROM users WHERE id = :id');
                    $mcStmt->execute([':id' => $found['id']]);
                    $mustChange = (bool)$mcStmt->fetchColumn();
                }
                if ($mustChange) {
                    header("Location: ../auth/change_password.php");
                    exit;
                }
            } catch (Exception $e) { /* column may not exist � skip silently */ }

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
        rotateSession();
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
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(:id1)) OR LOWER(TRIM(username)) = LOWER(TRIM(:id2)) LIMIT 1');
            $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $_SESSION['flash'] = 'Account not found.';
                header("Location: ../auth/forgot_password.php");
                exit;
            }

            // Always use email-based reset: require email on account
            if (empty($user['email'])) {
                $_SESSION['flash'] = 'Account has no email on file. Please contact administrator.';
                header("Location: ../auth/forgot_password.php");
                exit;
            }

            // Generate a one-time code and keep it only in the session.
            $code = generateVerificationCode();
            $expiresTs = time() + 15 * 60;
            $_SESSION['pending_reset_code'] = $code;
            $_SESSION['pending_reset_expires'] = $expiresTs;
            $subject = 'Password Reset Code';
            $message = getPasswordResetEmailTemplate($code);
            // Send to the real email address (no DB storage of the code).
            $sent = queueEmail($user['email'], $subject, $message, $user['id']);

            $_SESSION['pending_reset'] = [
                'user_id'   => $user['id'],
                'reset_type'=> 'email',
                'email'     => $user['email']
            ];

            $_SESSION['success'] = $sent
                ? 'A reset code was queued to your email. Please check your inbox.'
                : 'We generated a reset code but could not send the email. Please contact the administrator.';
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
    if (empty($found['email'])) {
        $_SESSION['flash'] = 'Account has no email on file. Please contact administrator.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }

    // File-based fallback: generate code, send email, store code in session
    $code = generateVerificationCode();
    $_SESSION['pending_reset_code'] = $code;
    $_SESSION['pending_reset_expires'] = time() + 15 * 60;
    $subject = 'Password Reset Code';
    $message = getPasswordResetEmailTemplate($code);
    $sent = queueEmail($found['email'], $subject, $message, $found['id'] ?? null);
    $_SESSION['pending_reset'] = [
        'user_id' => $found['id'],
        'reset_type' => 'email',
        'email' => $found['email']
    ];
    $_SESSION['success'] = $sent
        ? 'A reset code was queued to your email. Please check your inbox.'
        : 'We generated a reset code but could not send the email. Please contact the administrator.';
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

    // Verify the code stored in the session (no DB-based code storage).
    $stored = $_SESSION['pending_reset_code'] ?? null;
    $expiresTs = $_SESSION['pending_reset_expires'] ?? 0;
    if (!$stored || $stored !== $code || time() > $expiresTs) {
        $_SESSION['flash'] = 'Invalid or expired code. Please request a new reset.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }

    if ($pdo) {
        // Update password in the main users table using the remembered user_id.
        try {
            $pwHash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
            $stmt->execute([':p' => $pwHash, ':id' => $pending['user_id']]);

            unset($_SESSION['pending_reset'], $_SESSION['pending_reset_code'], $_SESSION['pending_reset_expires']);
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

    // File-based fallback: update password in users.json
    $users = _load_users();
    $idx = null;
    foreach ($users as $i => $u) {
        if ((string)($u['id'] ?? '') === (string)$pending['user_id']) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        $_SESSION['flash'] = 'Account not found.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }
    $users[$idx]['password'] = password_hash($new_password, PASSWORD_DEFAULT);
    _save_users($users);
    unset($_SESSION['pending_reset'], $_SESSION['pending_reset_code'], $_SESSION['pending_reset_expires']);
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

