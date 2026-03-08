<?php
// Usage: php send_test_code.php recipient@example.com
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../config/email.php';

$recipient = $argv[1] ?? 'johnlloydracaza09399561410@gmail.com';
$code = generateVerificationCode();

echo "Sending verification code $code to $recipient...\n";
$ok = sendVerificationCode($recipient, $code, 'password_reset');
if ($ok) {
    echo "Email queued/sent successfully.\n";
    exit(0);
} else {
    echo "Failed to send email. Check SMTP settings and logs.\n";
    exit(2);
}
