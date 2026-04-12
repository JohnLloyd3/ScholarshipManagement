<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/SurveyHelper.php';

startSecureSession();

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/surveys.php');
    exit;
}

$pdo    = getPDO();
$userId = $_SESSION['user_id'];
$role   = $_SESSION['user']['role'] ?? 'student';
$action = $_POST['action'] ?? '';

// ── CSRF validation ───────────────────────────────────────────────────────────
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = 'Invalid request token.';
    header('Location: ' . ($role === 'student' ? '../member/surveys.php' : '../admin/surveys.php'));
    exit;
}

function surveyBack(string $role): string {
    return $role === 'student' ? '../member/surveys.php' : '../admin/surveys.php';
}

// ── Admin-only actions ────────────────────────────────────────────────────────
if (in_array($action, ['create_survey', 'update_survey', 'delete_survey', 'update_status', 'save_questions'])) {
    if ($role !== 'admin') {
        $_SESSION['flash'] = 'Access denied.';
        header('Location: ' . surveyBack($role));
        exit;
    }
}

if ($action === 'create_survey') {
    $title = trim($_POST['title'] ?? '');
    if (!$title) { $_SESSION['flash'] = 'Title is required.'; header('Location: ../admin/surveys.php'); exit; }
    $id = createSurvey($pdo, ['title' => $title, 'description' => $_POST['description'] ?? null, 'scholarship_id' => $_POST['scholarship_id'] ?? null, 'cycle_label' => $_POST['cycle_label'] ?? null, 'created_by' => $userId]);
    // audit removed
    $_SESSION['success'] = 'Survey created.';
    header('Location: ../admin/survey_builder.php?id=' . $id);
    exit;
}

if ($action === 'update_survey') {
    $id = (int)($_POST['survey_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    if (!$id || !$title) { $_SESSION['flash'] = 'Invalid request.'; header('Location: ../admin/surveys.php'); exit; }
    updateSurvey($pdo, $id, ['title' => $title, 'description' => $_POST['description'] ?? null, 'scholarship_id' => $_POST['scholarship_id'] ?? null, 'cycle_label' => $_POST['cycle_label'] ?? null]);
    // audit removed
    $_SESSION['success'] = 'Survey updated.';
    header('Location: ../admin/surveys.php');
    exit;
}

if ($action === 'delete_survey') {
    $id = (int)($_POST['survey_id'] ?? 0);
    if (!deleteSurvey($pdo, $id)) {
        $_SESSION['flash'] = 'Cannot delete active or closed survey.';
    } else {
        // audit removed
        $_SESSION['success'] = 'Survey deleted.';
    }
    header('Location: ../admin/surveys.php');
    exit;
}

if ($action === 'update_status') {
    $id        = (int)($_POST['survey_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $survey    = getSurveyById($pdo, $id);

    if (!$survey) { $_SESSION['flash'] = 'Survey not found.'; header('Location: ../admin/surveys.php'); exit; }

    $allowed = ['draft' => 'active', 'active' => 'closed'];
    if (($allowed[$survey['status']] ?? '') !== $newStatus) {
        $_SESSION['flash'] = 'Invalid status transition.';
        header('Location: ../admin/surveys.php');
        exit;
    }

    // Cannot activate with no questions
    if ($newStatus === 'active' && count(getQuestions($pdo, $id)) === 0) {
        $_SESSION['flash'] = 'Cannot activate a survey with no questions.';
        header('Location: ../admin/surveys.php');
        exit;
    }

    $pdo->prepare("UPDATE surveys SET status=:s WHERE id=:id")->execute([':s' => $newStatus, ':id' => $id]);
    // audit removed

    // Notify eligible students when activating
    if ($newStatus === 'active') {
        $where = $survey['scholarship_id'] ? 'AND a.scholarship_id = :sch' : '';
        $params = $survey['scholarship_id'] ? [':sch' => $survey['scholarship_id']] : [];
        $stmt = $pdo->prepare("SELECT DISTINCT a.user_id FROM applications a WHERE a.status IN ('approved','completed') $where");
        $stmt->execute($params);
        $uids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (:uid, :title, :msg, 'info', NOW())");
        foreach ($uids as $uid) {
            $notif->execute([':uid' => $uid, ':title' => 'New Survey Available', ':msg' => 'A new survey "' . htmlspecialchars($survey['title']) . '" is available for you to complete.']);
        }
    }

    $_SESSION['success'] = 'Survey status updated to ' . $newStatus . '.';
    header('Location: ../admin/surveys.php');
    exit;
}

if ($action === 'save_questions') {
    $surveyId  = (int)($_POST['survey_id'] ?? 0);
    $questions = $_POST['questions'] ?? [];

    if (!$surveyId) { $_SESSION['flash'] = 'Invalid survey.'; header('Location: ../admin/surveys.php'); exit; }

    saveQuestions($pdo, $surveyId, $questions);
    // audit removed
    $_SESSION['success'] = 'Questions saved.';
    header('Location: ../admin/survey_builder.php?id=' . $surveyId);
    exit;
}

// ── Student: submit response ──────────────────────────────────────────────────
if ($action === 'submit_response') {
    if ($role !== 'student') { $_SESSION['flash'] = 'Access denied.'; header('Location: ' . surveyBack($role)); exit; }

    $surveyId = (int)($_POST['survey_id'] ?? 0);
    $survey   = getSurveyById($pdo, $surveyId);

    if (!$survey || $survey['status'] !== 'active') {
        $_SESSION['flash'] = 'Survey is not active.';
        header('Location: ../member/surveys.php');
        exit;
    }

    if (hasResponded($pdo, $surveyId, $userId)) {
        $_SESSION['flash'] = 'You have already submitted a response.';
        header('Location: ../member/surveys.php');
        exit;
    }

    $app = getEligibleApplicationForSurvey($pdo, $userId, $surveyId);
    if (!$app) {
        $_SESSION['flash'] = 'You are not eligible for this survey.';
        header('Location: ../member/surveys.php');
        exit;
    }

    // Validate required questions
    $questions = getQuestions($pdo, $surveyId);
    $answers   = $_POST['answers'] ?? [];
    $missing   = [];
    foreach ($questions as $q) {
        if ($q['required'] && empty($answers[$q['id']])) {
            $missing[] = $q['question'];
        }
    }
    if (!empty($missing)) {
        $_SESSION['flash'] = 'Please answer all required questions.';
        header('Location: ../member/surveys.php?id=' . $surveyId);
        exit;
    }

    try {
        $responseId = submitResponse($pdo, $surveyId, $userId, $app['id'], $answers);
        // audit removed
        $_SESSION['success'] = 'Survey submitted. Thank you!';
    } catch (Exception $e) {
        error_log('[SurveyController] ' . $e->getMessage());
        $_SESSION['flash'] = 'An error occurred.';
    }

    header('Location: ../member/surveys.php');
    exit;
}

$_SESSION['flash'] = 'Invalid request.';
header('Location: ' . surveyBack($role));
exit;
