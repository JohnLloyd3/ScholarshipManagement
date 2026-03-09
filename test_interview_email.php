<?php
/**
 * Test Interview Email Notification
 * Run this to test if interview emails are being sent
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/email.php';

echo "<h2>Testing Interview Email Notification</h2>";

// Test email details
$testEmail = 'leonelencarmem@gmail.com'; // Student email from context
$testName = 'Leonel';
$scholarshipTitle = 'Academic Excellence Award';
$interviewDate = '2026-03-15';
$interviewTime = '10:00:00';
$duration = 30;
$type = 'online';
$meetingLink = 'https://zoom.us/j/123456789';

echo "<p><strong>Sending test interview email to:</strong> $testEmail</p>";

$emailSubject = 'Interview Scheduled - ' . $scholarshipTitle;

$emailBody = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
$emailBody .= '<div style="background: #c41e3a; color: white; padding: 20px; text-align: center;">';
$emailBody .= '<h1 style="margin: 0;">📅 Interview Scheduled</h1>';
$emailBody .= '</div>';
$emailBody .= '<div style="padding: 30px; background: #f9f9f9;">';
$emailBody .= '<p style="font-size: 16px;">Dear ' . htmlspecialchars($testName) . ',</p>';
$emailBody .= '<p style="font-size: 16px;">Your interview has been scheduled for your scholarship application.</p>';

$emailBody .= '<div style="background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #c41e3a;">';
$emailBody .= '<h3 style="margin-top: 0; color: #c41e3a;">Interview Details</h3>';
$emailBody .= '<p style="margin: 10px 0;"><strong>Scholarship:</strong> ' . htmlspecialchars($scholarshipTitle) . '</p>';
$emailBody .= '<p style="margin: 10px 0;"><strong>Date:</strong> ' . date('F d, Y', strtotime($interviewDate)) . '</p>';
$emailBody .= '<p style="margin: 10px 0;"><strong>Time:</strong> ' . date('g:i A', strtotime($interviewTime)) . '</p>';
$emailBody .= '<p style="margin: 10px 0;"><strong>Duration:</strong> ' . $duration . ' minutes</p>';
$emailBody .= '<p style="margin: 10px 0;"><strong>Type:</strong> ' . ucfirst($type) . '</p>';
$emailBody .= '<p style="margin: 10px 0;"><strong>Meeting Link:</strong> <a href="' . htmlspecialchars($meetingLink) . '" style="color: #c41e3a;">' . htmlspecialchars($meetingLink) . '</a></p>';
$emailBody .= '</div>';

$emailBody .= '<p style="font-size: 14px; color: #666;">Please make sure to attend the interview on time. If you need to reschedule, please contact us as soon as possible.</p>';
$emailBody .= '<p style="font-size: 14px; color: #666;">Good luck with your interview!</p>';
$emailBody .= '</div>';
$emailBody .= '<div style="background: #333; color: white; padding: 15px; text-align: center; font-size: 12px;">';
$emailBody .= '<p style="margin: 0;">ScholarHub - Scholarship Management System</p>';
$emailBody .= '</div>';
$emailBody .= '</div>';

try {
    echo "<p>Attempting to send email...</p>";
    $result = sendEmail($testEmail, $emailSubject, $emailBody, true);
    
    if ($result) {
        echo "<p style='color: green; font-weight: bold;'>✅ Email sent successfully!</p>";
        echo "<p>Check the inbox for: $testEmail</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Email failed to send</p>";
        echo "<p>Check error logs for details</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>Email Configuration Check:</h3>";
echo "<pre>";
echo "SMTP Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'Not defined') . "\n";
echo "SMTP Port: " . (defined('SMTP_PORT') ? SMTP_PORT : 'Not defined') . "\n";
echo "SMTP User: " . (defined('SMTP_USER') ? SMTP_USER : 'Not defined') . "\n";
echo "SMTP From: " . (defined('SMTP_FROM') ? SMTP_FROM : 'Not defined') . "\n";
echo "</pre>";

echo "<hr>";
echo "<p><a href='admin/interview_slots.php'>← Back to Interview Slots</a></p>";
?>
