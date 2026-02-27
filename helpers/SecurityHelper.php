<?php
/**
 * Security & File Validation Helper
 * Provides secure file upload validation and security checks
 */

/**
 * Validate uploaded file for security
 * @param array $file $_FILES['filename'] array
 * @param array $allowed_types Array of allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array ['valid' => bool, 'error' => string]
 */
function validateFileUpload($file, $allowed_types = [], $max_size = 5242880) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error.'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'File exceeds maximum size of ' . ($max_size / 1024 / 1024) . 'MB.'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Default allowed types: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG
    if (empty($allowed_types)) {
        $allowed_types = ['application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg', 'image/png'];
    }
    
    if (!in_array($mime, $allowed_types)) {
        return ['valid' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types)];
    }
    
    // Check file extension matches MIME type
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $ext_mime = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    
    if (!isset($ext_mime[$ext]) || $ext_mime[$ext] !== $mime) {
        return ['valid' => false, 'error' => 'File extension does not match file type.'];
    }
    
    return ['valid' => true, 'mime' => $mime];
}

/**
 * Sanitize filename for safe storage
 * @param string $filename Original filename
 * @return string Sanitized filename
 */
function sanitizeFilename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
    return $filename;
}

/**
 * Generate secure random token
 * @param int $length Token length
 * @return string Random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate CSRF token (if implemented)
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token for session
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken();
    }
    return $_SESSION['csrf_token'];
}
/**
 * Hash password using bcrypt
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password against hash
 * @param string $password Plain text password
 * @param string $hash Password hash
 * @return bool True if matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validate email format
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * Requirements: min 8 chars, 1 uppercase, 1 lowercase, 1 number
 * @param string $password Password to validate
 * @return bool True if strong
 */
function isStrongPassword($password) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

/**
 * Validate GPA format (0.0 - 4.0)
 * @param float $gpa GPA to validate
 * @return bool True if valid
 */
function isValidGPA($gpa) {
    $gpa = floatval($gpa);
    return $gpa >= 0.0 && $gpa <= 4.0;
}

/**
 * Validate phone number
 * @param string $phone Phone to validate
 * @return bool True if valid
 */
function isValidPhone($phone) {
    return preg_match('/^[0-9\-\+\s\(\)]{10,}$/', $phone);
}

/**
 * Sanitize string input
 * @param string $string String to sanitize
 * @return string Sanitized string
 */
function sanitizeString($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize email
 * @param string $email Email to sanitize
 * @return string Sanitized email
 */
function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Sanitize integer input and return as int (or 0 if invalid)
 * @param mixed $value Value to sanitize
 * @return int
 */
function sanitizeInt($value) {
    if (is_numeric($value)) {
        return (int) $value;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered !== false ? (int) $filtered : 0;
}

/**
 * Sanitize float input and return as float (or 0.0 if invalid)
 * @param mixed $value Value to sanitize
 * @return float
 */
function sanitizeFloat($value) {
    if (is_numeric($value)) {
        return (float) $value;
    }
    $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $filtered !== false ? (float) $filtered : 0.0;
}

/**
 * Generate a safe filename suitable for storing on disk.
 * Strips dangerous characters and adds a unique prefix.
 * @param string $filename Original filename
 * @return string Safe filename
 */
function generateSafeFileName($filename) {
    // remove path information
    $base = basename($filename);
    // replace any character that is not alphanumeric, dot, underscore or hyphen
    $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base);
    return uniqid('', true) . '_' . $safe;
}

/**
 * Get client IP address
 * @return string Client IP
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    return filter_var(trim($ip), FILTER_VALIDATE_IP) ?: '127.0.0.1';
}

/**
 * Generate numeric verification code
 * @param int $length Code length
 * @return string Numeric code
 */
function generateVerificationCode($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Check if user is logged in
 * @return bool True if logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user from session
 * @return array|null User data or null
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID
 * @return int|null User ID or null
 */
function getCurrentUserID() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user has role
 * @param string $role Role to check
 * @return bool True if user has role
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Check if user has any of the roles
 * @param array $roles Roles to check
 * @return bool True if user has any role
 */
function hasAnyRole($roles) {
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

/**
 * Redirect to login if not authenticated
 * @param string $redirectTo URL to redirect after login (optional)
 */
function requireLogin($redirectTo = null) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $redirectTo ?? $_SERVER['REQUEST_URI'];
        header('Location: ../auth/login.php');
        exit;
    }
}

/**
 * Redirect if no permission
 * @param string $role Required role
 * @param string $message Error message
 */
function requireRole($role, $message = 'Access Denied') {
    if (!hasRole($role)) {
        $_SESSION['error'] = $message;
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Redirect if no permission for any role
 * @param array $roles Allowed roles
 * @param string $message Error message
 */
function requireAnyRole($roles, $message = 'Access Denied') {
    if (!hasAnyRole($roles)) {
        $_SESSION['error'] = $message;
        header('Location: ../index.php');
        exit;
    }
}