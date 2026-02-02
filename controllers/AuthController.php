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

if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Validate role - only allow student, staff, and reviewer for public registration
    $allowedRoles = ['student', 'staff', 'reviewer'];
    if (!in_array($role, $allowedRoles)) {
        $_SESSION['flash'] = "Please select a valid account type.";
        header("Location: ../auth/register.php");
        exit;
    }

    if ($username === '' || $password === '' || $first === '' || $last === '' || $email === '' || $role === '') {
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
            $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, phone, address, role, email_verified, active) VALUES (:u, :p, :f, :l, :e, :ph, :a, :r, 0, 1)');
            $stmt->execute([
                ':u' => $username,
                ':p' => $pwHash,
                ':f' => $first,
                ':l' => $last,
                ':e' => $email,
                ':ph' => $phone,
                ':a' => $address,
                ':r' => $role
            ]);

            $id = $pdo->lastInsertId();

            // Generate and send verification code
            $code = generateVerificationCode();
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $pdo->prepare('INSERT INTO email_verification_codes (user_id, email, code, type, expires_at) VALUES (:uid, :e, :c, :t, :exp)');
            $stmt->execute([
                ':uid' => $id,
                ':e' => $email,
                ':c' => $code,
                ':t' => 'verification',
                ':exp' => $expires
            ]);

            // Send verification email
            sendVerificationCode($email, $code, 'verification');

            $_SESSION['pending_verification'] = $email;
            $_SESSION['success'] = 'Registration successful! Please check your email for verification code.';
            header("Location: ../auth/verify_email.php");
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
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'created_at' => date('c'),
            'role' => $role
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
            header("Location: ../member/dashboard.php");
            exit;
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
            $stmt = $pdo->prepare('SELECT id, username, password, first_name, last_name, email, role, active, email_verified FROM users WHERE username = :u LIMIT 1');
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

            // Check if email is verified - if not, require verification
            if (!$found['email_verified']) {
                $_SESSION['pending_verification'] = $found['email'];
                $_SESSION['flash'] = 'Please verify your email first.';
                header("Location: ../auth/verify_email.php");
                exit;
            }

            // Generate and send login verification code
            $code = generateVerificationCode();
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $stmt = $pdo->prepare('INSERT INTO email_verification_codes (user_id, email, code, type, expires_at) VALUES (:uid, :e, :c, :t, :exp)');
            $stmt->execute([
                ':uid' => $found['id'],
                ':e' => $found['email'],
                ':c' => $code,
                ':t' => 'login',
                ':exp' => $expires
            ]);

            sendVerificationCode($found['email'], $code, 'login');

            $_SESSION['pending_login'] = [
                'user_id' => $found['id'],
                'email' => $found['email']
            ];
            $_SESSION['success'] = 'Verification code sent to your email. Please enter it to complete login.';
            header("Location: ../auth/verify_login.php");
            exit;

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

        $_SESSION['user_id'] = $found['id'];
        $_SESSION['user'] = [
            'username' => $found['username'],
            'first_name' => $found['first_name'],
            'last_name' => $found['last_name'],
            'email' => $found['email'],
            'role' => $found['role'] ?? 'student'
        ];
        $_SESSION['success'] = 'Welcome back (local user).';

        header("Location: ../member/dashboard.php");
        exit;
    }

} elseif ($action === 'verify_email') {
    $code = trim($_POST['code'] ?? '');
    $email = $_SESSION['pending_verification'] ?? '';

    if (!$email || !$code) {
        $_SESSION['flash'] = 'Invalid verification request.';
        header("Location: ../auth/verify_email.php");
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM email_verification_codes WHERE email = :e AND code = :c AND type = :t AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([':e' => $email, ':c' => $code, ':t' => 'verification']);
            $verification = $stmt->fetch();

            if (!$verification) {
                $_SESSION['flash'] = 'Invalid or expired verification code.';
                header("Location: ../auth/verify_email.php");
                exit;
            }

            // Mark code as used
            $stmt = $pdo->prepare('UPDATE email_verification_codes SET used = 1 WHERE id = :id');
            $stmt->execute([':id' => $verification['id']]);

            // Verify user email
            $stmt = $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = :id');
            $stmt->execute([':id' => $verification['user_id']]);

            // Get user and log them in
            $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, role FROM users WHERE id = :id');
            $stmt->execute([':id' => $verification['user_id']]);
            $user = $stmt->fetch();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'student'
            ];
            unset($_SESSION['pending_verification']);
            $_SESSION['success'] = 'Email verified successfully!';

            header("Location: ../member/dashboard.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Verification failed. Try again.';
            header("Location: ../auth/verify_email.php");
            exit;
        }
    }
} elseif ($action === 'verify_login') {
    $code = trim($_POST['code'] ?? '');
    $pending = $_SESSION['pending_login'] ?? null;

    if (!$pending || !$code) {
        $_SESSION['flash'] = 'Invalid verification request.';
        header("Location: ../auth/verify_login.php");
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM email_verification_codes WHERE user_id = :uid AND email = :e AND code = :c AND type = :t AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([':uid' => $pending['user_id'], ':e' => $pending['email'], ':c' => $code, ':t' => 'login']);
            $verification = $stmt->fetch();

            if (!$verification) {
                $_SESSION['flash'] = 'Invalid or expired verification code.';
                header("Location: ../auth/verify_login.php");
                exit;
            }

            // Mark code as used
            $stmt = $pdo->prepare('UPDATE email_verification_codes SET used = 1 WHERE id = :id');
            $stmt->execute([':id' => $verification['id']]);

            // Get user and log them in
            $stmt = $pdo->prepare('SELECT id, username, first_name, last_name, email, role FROM users WHERE id = :id');
            $stmt->execute([':id' => $pending['user_id']]);
            $user = $stmt->fetch();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'student'
            ];
            unset($_SESSION['pending_login']);
            $_SESSION['success'] = 'Welcome back, ' . ($user['first_name'] ?? $user['username']);

            header("Location: ../member/dashboard.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Verification failed. Try again.';
            header("Location: ../auth/verify_login.php");
            exit;
        }
    }
} elseif ($action === 'request_password_reset') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $_SESSION['flash'] = 'Please provide your email address.';
        header("Location: ../auth/forgot_password.php");
        exit;
    }

    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate reset code
                $code = generateVerificationCode();
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmt = $pdo->prepare('INSERT INTO email_verification_codes (user_id, email, code, type, expires_at) VALUES (:uid, :e, :c, :t, :exp)');
                $stmt->execute([
                    ':uid' => $user['id'],
                    ':e' => $email,
                    ':c' => $code,
                    ':t' => 'password_reset',
                    ':exp' => $expires
                ]);

                sendVerificationCode($email, $code, 'password_reset');
            }

            // Always show success message for security
            $_SESSION['success'] = 'If an account exists with that email, a password reset code has been sent.';
            $_SESSION['pending_reset'] = $email;
            header("Location: ../auth/reset_password.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = 'Error processing request. Try again.';
            header("Location: ../auth/forgot_password.php");
            exit;
        }
    }
} elseif ($action === 'reset_password') {
    $code = trim($_POST['code'] ?? '');
    $email = $_SESSION['pending_reset'] ?? trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$email || !$code || !$new_password) {
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
            $stmt = $pdo->prepare('SELECT * FROM email_verification_codes WHERE email = :e AND code = :c AND type = :t AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([':e' => $email, ':c' => $code, ':t' => 'password_reset']);
            $verification = $stmt->fetch();

            if (!$verification) {
                $_SESSION['flash'] = 'Invalid or expired reset code.';
                header("Location: ../auth/reset_password.php");
                exit;
            }

            // Mark code as used
            $stmt = $pdo->prepare('UPDATE email_verification_codes SET used = 1 WHERE id = :id');
            $stmt->execute([':id' => $verification['id']]);

            // Update password
            $pwHash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = :p WHERE id = :id');
            $stmt->execute([':p' => $pwHash, ':id' => $verification['user_id']]);

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
} else {
    header("Location: ../auth/login.php");
    exit;
}

function _error_db($e)
{
    // For local development, you can log error messages to a file.
    // error_log($e->getMessage());
}

