<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user']['role'] ?? '') !== 'reviewer') {
    $_SESSION['flash'] = 'Reviewer access only.';
    header('Location: ../auth/login.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getPDO();

if ($action === 'approve' || $action === 'reject') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt = $pdo->prepare('UPDATE reviews SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);
        $_SESSION['success'] = 'Review updated.';
    }
    header('Location: ../reviewer/dashboard.php');
    exit;
}

$_SESSION['flash'] = 'Unknown action.';
header('Location: ../reviewer/dashboard.php');
exit;