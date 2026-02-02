<?php
// Simple auth helpers

// Base URL for redirects inside this app (XAMPP: http://localhost/ScholarshipManagement/)
if (!defined('APP_BASE')) {
    define('APP_BASE', '/ScholarshipManagement');
}

function require_login()
{
    session_start();
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash'] = 'Please log in.';
        header('Location: ' . APP_BASE . '/auth/login.php');
        exit;
    }
    
    // Check if user account is still active
    require_once __DIR__ . '/../config/db.php';
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT active FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && !$user['active']) {
            session_destroy();
            $_SESSION['flash'] = 'Your account has been deactivated.';
            header('Location: ' . APP_BASE . '/auth/login.php');
            exit;
        }
    } catch (Exception $e) {
        // Continue if DB check fails
    }
}

function current_user()
{
    session_start();
    return $_SESSION['user'] ?? null;
}

function require_role($role)
{
    session_start();
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash'] = 'Please log in.';
        header('Location: ' . APP_BASE . '/auth/login.php');
        exit;
    }
    $r = $_SESSION['user']['role'] ?? 'student';
    $allowed = is_array($role) ? $role : [$role];
    if (!in_array($r, $allowed, true)) {
        $_SESSION['flash'] = 'Access denied.';
        header('Location: ' . APP_BASE . '/auth/login.php');
        exit;
    }
}

function is_role($role)
{
    session_start();
    return (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === $role);
}