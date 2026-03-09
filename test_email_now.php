<?php
/**
 * Quick Email Test Script
 * Run this to test if emails are working
 */

require_once __DIR__ . '/config/email.php';

echo "<h2>Email Test</h2>";
echo "<p>Testing email configuration...</p>";

// Test email address - change this to your email
$testEmail = 'leonelencarmem@gmail.com';
$subject = 'Test Email from ScholarHub';
$message = '<h2>Test Email</h2><p>If you receive this, email is working!</p><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';

echo "<p>Sending test email to: <strong>$testEmail</strong></p>";
echo "<p>SMTP Host: " . SMTP_HOST . "</p>";
echo "<p>SMTP Port: " . SMTP_PORT . "</p>";
echo "<p>SMTP User: " . SMTP_USER . "</p>";
echo "<p>Attempting to send...</p>";

$result = sendEmail($testEmail, $subject, $message, true);

if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! Email sent successfully!</p>";
    echo "<p>Check your inbox (and spam folder) for the test email.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED! Email could not be sent.</p>";
    echo "<p>Check PHP error log for details.</p>";
    echo "<p>Common issues:</p>";
    echo "<ul>";
    echo "<li>Gmail App Password incorrect</li>";
    echo "<li>OpenSSL not enabled in PHP</li>";
    echo "<li>Firewall blocking port 587</li>";
    echo "<li>Gmail account security settings</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Configuration Check:</h3>";
echo "<pre>";
echo "SMTP_ENABLED: " . (SMTP_ENABLED ? 'Yes' : 'No') . "\n";
echo "SMTP_HOST: " . SMTP_HOST . "\n";
echo "SMTP_PORT: " . SMTP_PORT . "\n";
echo "SMTP_USER: " . SMTP_USER . "\n";
echo "SMTP_PASS: " . (SMTP_PASS ? '****** (set)' : 'NOT SET') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'Enabled' : 'DISABLED - THIS IS THE PROBLEM!') . "\n";
echo "</pre>";

if (!extension_loaded('openssl')) {
    echo "<div style='background: #fee; padding: 20px; border: 2px solid red; margin: 20px 0;'>";
    echo "<h3 style='color: red;'>⚠️ OpenSSL Not Enabled!</h3>";
    echo "<p>To enable OpenSSL in XAMPP:</p>";
    echo "<ol>";
    echo "<li>Open: <code>C:\\xampp\\php\\php.ini</code></li>";
    echo "<li>Find: <code>;extension=openssl</code></li>";
    echo "<li>Remove the semicolon: <code>extension=openssl</code></li>";
    echo "<li>Save and restart Apache</li>";
    echo "</ol>";
    echo "</div>";
}
?>
