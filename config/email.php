<?php
/**
 * Email Service Helper
 * Handles sending verification codes and other emails
 */

// Email configuration - update these for your SMTP server
define('EMAIL_FROM', 'noreply@scholarshipmanagement.com');
define('EMAIL_FROM_NAME', 'Scholarship Management System');
define('SMTP_ENABLED', false); // Set to true if using SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');

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
 * For now, falls back to PHP mail()
 */
function sendEmailSMTP($to, $subject, $message, $html = true) {
    // For production, integrate PHPMailer here
    // For now, use PHP mail()
    return sendEmailPHP($to, $subject, $message, $html);
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
 * Generate a random 6-digit code
 */
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}
