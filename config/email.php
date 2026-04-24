<?php
/**
 * Email Service Helper
 * Handles sending verification codes and other emails
 */

// Email configuration.
// Hardcoded credentials take priority, then DB settings, then env vars.

function _getEmailSetting(string $key, string $envVar, string $default = ''): string {
    static $dbSettings = null;
    if ($dbSettings === null) {
        $dbSettings = [];
        try {
            if (function_exists('getPDO')) {
                $pdo = getPDO();
                $rows = $pdo->query('SELECT `key`, `value` FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
                $dbSettings = $rows ?: [];
            }
        } catch (Exception $e) { /* table may not exist yet */ }
    }
    // DB setting only wins if it's non-empty AND the default is empty
    // (i.e. admin explicitly changed it via UI)
    if (isset($dbSettings[$key]) && $dbSettings[$key] !== '' && $default === '') {
        return $dbSettings[$key];
    }
    // Hardcoded default wins when provided
    if ($default !== '') return $default;
    $env = getenv($envVar);
    return ($env !== false && $env !== '') ? $env : '';
}

if (!defined('EMAIL_FROM_NAME')) {
    define('EMAIL_FROM_NAME', 'ScholarHub');
}
if (!defined('SMTP_ENABLED')) {
    define('SMTP_ENABLED', true);
}
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', getenv('SMTP_USER') ?: 'your-email@gmail.com');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getenv('SMTP_PASS') ?: 'your-app-password');
}
if (!defined('EMAIL_FROM')) {
    define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'noreply@scholarhub.com');
}
/**
 * Send email using PHP mail() or SMTP
 */
function sendEmail($to, $subject, $message, $html = true) {
    if (SMTP_ENABLED) {
        return sendEmailSMTP($to, $subject, $message, $html);
    } else {
        return sendEmailPHP($to, $subject, $message, $html);
    }
}

/**
 * Send email using PHP mail() function
 */
function sendEmailPHP($to, $subject, $message, $html = true) {
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    if ($html) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Send email using SMTP (requires PHPMailer or similar)
 * Implemented with a small SMTP client (STARTTLS) so it works on XAMPP without extra dependencies.
 */
function sendEmailSMTP($to, $subject, $message, $html = true) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;

    if (!$host || !$port || !$user || !$pass) {
        error_log('[SMTP Error] Missing SMTP configuration.');
        return false;
    }

    $timeout = 15;
    $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        error_log("[SMTP Error] Connection failed: {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp) {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $data .= $line;
            // multi-line responses have a hyphen after the code, final line has space
            if (preg_match('/^\d{3}\s/', $line)) break;
        }
        return $data;
    };

    $write = function ($cmd) use ($fp) {
        return fwrite($fp, $cmd . "\r\n");
    };

    $expect = function ($resp, $codes) {
        foreach ((array)$codes as $code) {
            if (strpos($resp, (string)$code) === 0) return true;
        }
        return false;
    };

    $banner = $read();
    if (!$expect($banner, 220)) {
        error_log('[SMTP Error] Invalid banner: ' . trim($banner));
        fclose($fp);
        return false;
    }

    $write('EHLO localhost');
    $ehlo = $read();
    if (!$expect($ehlo, 250)) {
        $write('HELO localhost');
        $helo = $read();
        if (!$expect($helo, 250)) {
            error_log('[SMTP Error] EHLO/HELO failed: ' . trim($ehlo . $helo));
            fclose($fp);
            return false;
        }
    }

    // STARTTLS
    $write('STARTTLS');
    $starttls = $read();
    if (!$expect($starttls, 220)) {
        error_log('[SMTP Error] STARTTLS failed: ' . trim($starttls));
        fclose($fp);
        return false;
    }

    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log('[SMTP Error] TLS negotiation failed. Ensure PHP OpenSSL is enabled in XAMPP.');
        fclose($fp);
        return false;
    }

    // EHLO again after TLS
    $write('EHLO localhost');
    $ehlo2 = $read();
    if (!$expect($ehlo2, 250)) {
        error_log('[SMTP Error] EHLO after TLS failed: ' . trim($ehlo2));
        fclose($fp);
        return false;
    }

    // AUTH LOGIN
    $write('AUTH LOGIN');
    $auth = $read();
    if (!$expect($auth, 334)) {
        error_log('[SMTP Error] AUTH LOGIN not accepted: ' . trim($auth));
        fclose($fp);
        return false;
    }

    $write(base64_encode($user));
    $uResp = $read();
    if (!$expect($uResp, 334)) {
        error_log('[SMTP Error] Username rejected: ' . trim($uResp));
        fclose($fp);
        return false;
    }

    $write(base64_encode($pass));
    $pResp = $read();
    if (!$expect($pResp, 235)) {
        error_log('[SMTP Error] Password rejected: ' . trim($pResp));
        fclose($fp);
        return false;
    }

    // Use the authenticated SMTP user as the MAIL FROM/envelope sender to
    // avoid spoofing issues and improve deliverability with Gmail.
    $from = $user ?: EMAIL_FROM;
    $fromName = EMAIL_FROM_NAME;

    $write('MAIL FROM:<' . $from . '>');
    $mf = $read();
    if (!$expect($mf, 250)) {
        error_log('[SMTP Error] MAIL FROM failed: ' . trim($mf));
        fclose($fp);
        return false;
    }

    $write('RCPT TO:<' . $to . '>');
    $rt = $read();
    if (!$expect($rt, [250, 251])) {
        error_log('[SMTP Error] RCPT TO failed: ' . trim($rt));
        fclose($fp);
        return false;
    }

    $write('DATA');
    $dataResp = $read();
    if (!$expect($dataResp, 354)) {
        error_log('[SMTP Error] DATA not accepted: ' . trim($dataResp));
        fclose($fp);
        return false;
    }

    // Headers + body
    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $from . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $html
        ? 'Content-Type: text/html; charset=UTF-8'
        : 'Content-Type: text/plain; charset=UTF-8';

    // Normalize line endings and dot-stuff per SMTP rules
    $body = str_replace(["\r\n", "\r"], "\n", $message);
    $body = preg_replace("/\n\./", "\n..", $body);
    $body = str_replace("\n", "\r\n", $body);

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    fwrite($fp, $payload . "\r\n");

    $sent = $read();
    if (!$expect($sent, 250)) {
        error_log('[SMTP Error] Message not accepted: ' . trim($sent));
        fclose($fp);
        return false;
    }

    $write('QUIT');
    fclose($fp);
    return true;
}

/**
 * Send verification code email
 */
function sendVerificationCode($email, $code, $type = 'verification') {
    $subject = '';
    $message = '';
    
    switch ($type) {
        case 'verification':
            $subject = 'Email Verification Code - Scholarship Management System';
            $message = getVerificationEmailTemplate($code);
            break;
        case 'login':
            $subject = 'Login Verification Code - Scholarship Management System';
            $message = getLoginCodeEmailTemplate($code);
            break;
        case 'password_reset':
            $subject = 'Password Reset Code - Scholarship Management System';
            $message = getPasswordResetEmailTemplate($code);
            break;
    }
    
    return sendEmail($email, $subject, $message, true);
}

/**
 * Email template for verification
 */
function getVerificationEmailTemplate($code) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Email Verification</h2>
            <p>Thank you for registering with Scholarship Management System. Please use the following code to verify your email address:</p>
            <div class='code-box'>{$code}</div>
            <p>This code will expire in 15 minutes.</p>
            <p>If you did not request this code, please ignore this email.</p>
            <div class='footer'>
                <p>© " . date('Y') . " Scholarship Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Email template for login code
 */
function getLoginCodeEmailTemplate($code) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Login Verification Code</h2>
            <p>You have requested to log in to your account. Please use the following verification code:</p>
            <div class='code-box'>{$code}</div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you did not request this code, please secure your account immediately.</p>
            <div class='footer'>
                <p>© " . date('Y') . " Scholarship Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Email template for password reset
 */
function getPasswordResetEmailTemplate($code) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .code-box { background: #f4f4f4; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; margin: 20px 0; border-radius: 5px; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Password Reset Code</h2>
            <p>You have requested to reset your password. Please use the following code to proceed:</p>
            <div class='code-box'>{$code}</div>
            <p>This code will expire in 15 minutes.</p>
            <p>If you did not request a password reset, please ignore this email and secure your account.</p>
            <div class='footer'>
                <p>© " . date('Y') . " Scholarship Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send application decision notification email to applicant (3.6 Notify Applicants)
 */
function sendApplicationDecisionEmail($to, $applicantName, $scholarshipTitle, $status, $comments = '') {
    $subject = 'Scholarship Application Update - ' . ($status === 'approved' ? 'Approved' : 'Update');
    $message = getApplicationDecisionEmailTemplate($applicantName, $scholarshipTitle, $status, $comments);
    return sendEmail($to, $subject, $message, true);
}

/**
 * Queue an email to be sent by the background processor.
 * Also attempts to send immediately so critical flows
 * (like password reset) work even if cron is not running.
 * Falls back to direct send if queueing fails.
 */
function queueEmail($to, $subject, $message, $user_id = null) {
    // If DB helper is not available for some reason, just send directly.
    try {
        if (!function_exists('getPDO')) {
            return sendEmail($to, $subject, $message, true);
        }

        $pdo = getPDO();

        // Defensive: if `email_logs` table is not present, skip queueing and send immediately
        try {
            $pdo->query('SELECT 1 FROM email_logs LIMIT 1');
        } catch (Exception $e) {
            return sendEmail($to, $subject, $message, true);
        }

        // First, record the email in the log as queued.
        $stmt = $pdo->prepare('INSERT INTO email_logs (user_id, email, subject, body, status, attempts, created_at) VALUES (:uid, :email, :subject, :body, "queued", 0, NOW())');
        $stmt->execute([
            ':uid' => $user_id,
            ':email' => $to,
            ':subject' => $subject,
            ':body' => $message
        ]);

        $emailId = (int)$pdo->lastInsertId();

        // Attempt to send immediately so the user receives the email
        // even if the cron-based queue processor is not running.
        $sent = sendEmail($to, $subject, $message, true);

        // Best-effort update of log status; do not throw if this fails.
        try {
            $update = $pdo->prepare('UPDATE email_logs SET status = :status, attempts = attempts + 1, last_attempt_at = NOW() WHERE id = :id');
            $update->execute([
                ':status' => $sent ? 'sent' : 'failed',
                ':id' => $emailId
            ]);
        } catch (Exception $inner) {
            error_log('[queueEmail] failed to update email_logs status: ' . $inner->getMessage());
        }

        return $sent;
    } catch (Exception $e) {
        error_log('[queueEmail] failed to queue: ' . $e->getMessage());
        // Fallback to immediate send if logging/DB fails.
        return sendEmail($to, $subject, $message, true);
    }
}

/**
 * Email template for application approved/rejected notification
 */
function getApplicationDecisionEmailTemplate($applicantName, $scholarshipTitle, $status, $comments = '') {
    $isApproved = ($status === 'approved');
    $statusLabel = $isApproved ? 'Approved' : 'Rejected';
    $statusColor = $isApproved ? '#2e7d32' : '#c62828';
    $statusText = $isApproved
        ? 'Congratulations! Your scholarship application has been <strong style="color:' . $statusColor . '">approved</strong>.'
        : 'Your scholarship application has been <strong style="color:' . $statusColor . '">rejected</strong>.';
    $commentsHtml = $comments ? '<p><strong>Comments:</strong> ' . htmlspecialchars($comments) . '</p>' : '';
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .status-box { padding: 16px; border-radius: 8px; margin: 20px 0; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Scholarship Application Update</h2>
            <p>Hello " . htmlspecialchars($applicantName) . ",</p>
            <p>Your application for <strong>" . htmlspecialchars($scholarshipTitle) . "</strong> has been reviewed.</p>
            <p>" . $statusText . "</p>
            " . $commentsHtml . "
            <p>Log in to your account to view full details.</p>
            <div class='footer'>
                <p>© " . date('Y') . " Scholarship Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
