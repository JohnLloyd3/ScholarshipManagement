<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/FeedbackHelper.php';

startSecureSession();

requireLogin();
requireRole('student', 'Student access required');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../students/feedback.php');
    exit;
}

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'submit_feedback') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = 'Invalid request token.';
        header('Location: ../students/feedback.php');
        exit;
    }

    $applicationId = (int)($_POST['application_id'] ?? 0);
    $rating        = (int)($_POST['rating'] ?? 0);
    $comment       = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $_SESSION['flash'] = 'Rating must be between 1 and 5.';
        header('Location: ../students/feedback.php');
        exit;
    }

    // Verify application belongs to user and is eligible
    $stmt = $pdo->prepare("SELECT id, status FROM applications WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $applicationId, ':uid' => $userId]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app || !in_array($app['status'], ['approved', 'completed'])) {
        $_SESSION['flash'] = 'Application not eligible for feedback.';
        header('Location: ../students/feedback.php');
        exit;
    }

    if (feedbackExists($pdo, $applicationId)) {
        $_SESSION['flash'] = 'You have already submitted feedback for this application.';
        header('Location: ../students/feedback.php');
        exit;
    }

    try {
        $id = submitFeedback($pdo, $userId, $applicationId, $rating, $comment ?: null);
        // audit removed
        $_SESSION['success'] = 'Thank you for your feedback!';
    } catch (Exception $e) {
        error_log('[FeedbackController] ' . $e->getMessage());
        $_SESSION['flash'] = 'An error occurred. Please try again.';
    }
}

header('Location: ../students/feedback.php');
exit;
