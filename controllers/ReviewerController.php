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
        // Update review record
        $stmt = $pdo->prepare('UPDATE reviews SET status = :s WHERE id = :id');
        $stmt->execute([':s' => $status, ':id' => $id]);

        // Also update the linked application status
        $stmt = $pdo->prepare('SELECT application_id FROM reviews WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['application_id'])) {
            $appStatus = ($status === 'approved') ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE applications SET status = :s WHERE id = :aid')
                ->execute([':s' => $appStatus, ':aid' => $row['application_id']]);
        }
        $_SESSION['success'] = 'Review updated.';
    }
    header('Location: ../reviewer/dashboard.php');
    exit;
}

$_SESSION['flash'] = 'Unknown action.';
header('Location: ../reviewer/dashboard.php');
exit;