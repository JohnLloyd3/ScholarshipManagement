<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/email.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

$testEmail = $argv[1] ?? 'johnlloydracaza09399561410@gmail.com';
$testUser = 'testuser_' . time();
$pw = 'TestPass123';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo "DB unavailable: " . $e->getMessage() . "\n";
    exit(1);
}

// Check if email already present
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $testEmail]);
$found = $stmt->fetchColumn();

if ($found) {
    echo "User with email {$testEmail} already exists (id={$found}). Will use existing account for reset test.\n";
    $userId = $found;
} else {
    // Create a test user
    $pwHash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, first_name, last_name, email, role, email_verified, active, created_at) VALUES (:u, :p, :f, :l, :e, :r, 1, 1, NOW())');
    $stmt->execute([
        ':u' => $testUser,
        ':p' => $pwHash,
        ':f' => 'Test',
        ':l' => 'User',
        ':e' => $testEmail,
        ':r' => 'student'
    ]);
    $userId = $pdo->lastInsertId();
    echo "Created test user: {$testUser} (id={$userId}) with email {$testEmail}.\n";
}

// Generate reset code and persist
$code = generateVerificationCode();
$expires = date('Y-m-d H:i:s', time() + 15 * 60);
try {
    // Attempt to insert into email_verification_codes if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_verification_codes'");
    $exists = (bool)$stmt->fetch();
    if ($exists) {
        $ins = $pdo->prepare('INSERT INTO email_verification_codes (user_id, email, code, type, expires_at, used, created_at) VALUES (:uid, :email, :code, :type, :exp, 0, NOW())');
        $ins->execute([
            ':uid' => $userId,
            ':email' => $testEmail,
            ':code' => $code,
            ':type' => 'password_reset',
            ':exp' => $expires
        ]);
        echo "Inserted code into email_verification_codes table.\n";
    } else {
        echo "email_verification_codes table not found; will rely on queued email only.\n";
    }
} catch (PDOException $e) {
    echo "Failed to persist code: " . $e->getMessage() . "\n";
}

// Send verification email
$sent = sendVerificationCode($testEmail, $code, 'password_reset');
if ($sent) {
    echo "Password reset code sent to {$testEmail}: {$code}\n";
} else {
    echo "Failed to send password reset email to {$testEmail}. Check SMTP settings.\n";
}

echo "Test registration+reset complete. Use the code above to reset the password via the web UI.\n";
