<?php
/**
 * Survey Helper — CRUD, questions, responses, analytics
 */

function createSurvey(PDO $pdo, array $data): int {
    $stmt = $pdo->prepare("INSERT INTO surveys (title, description, scholarship_id, cycle_label, status, created_by) VALUES (:title, :desc, :sch, :cycle, 'draft', :by)");
    $stmt->execute([':title' => $data['title'], ':desc' => $data['description'] ?? null, ':sch' => $data['scholarship_id'] ?: null, ':cycle' => $data['cycle_label'] ?? null, ':by' => $data['created_by']]);
    return (int)$pdo->lastInsertId();
}

function updateSurvey(PDO $pdo, int $id, array $data): bool {
    $stmt = $pdo->prepare("UPDATE surveys SET title=:title, description=:desc, scholarship_id=:sch, cycle_label=:cycle WHERE id=:id");
    return $stmt->execute([':title' => $data['title'], ':desc' => $data['description'] ?? null, ':sch' => $data['scholarship_id'] ?: null, ':cycle' => $data['cycle_label'] ?? null, ':id' => $id]);
}

function deleteSurvey(PDO $pdo, int $id): bool {
    $stmt = $pdo->prepare("DELETE FROM surveys WHERE id=:id AND status='draft'");
    return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
}

function getSurveyById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT s.*, sch.title AS scholarship_title FROM surveys s LEFT JOIN scholarships sch ON s.scholarship_id = sch.id WHERE s.id=:id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getAllSurveys(PDO $pdo, ?string $status = null): array {
    try {
        $where = $status ? 'WHERE s.status = :status' : '';
        $params = $status ? [':status' => $status] : [];
        $stmt = $pdo->prepare("
            SELECT s.*, sch.title AS scholarship_title,
                   (SELECT COUNT(*) FROM survey_responses sr WHERE sr.survey_id = s.id) AS response_count
            FROM surveys s
            LEFT JOIN scholarships sch ON s.scholarship_id = sch.id
            $where
            ORDER BY s.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function saveQuestions(PDO $pdo, int $surveyId, array $questions): bool {
    $pdo->prepare("DELETE FROM survey_questions WHERE survey_id=:id")->execute([':id' => $surveyId]);
    $stmt = $pdo->prepare("INSERT INTO survey_questions (survey_id, question, type, options, sort_order, required) VALUES (:sid, :q, :type, :opts, :order, :req)");
    foreach ($questions as $i => $q) {
        $opts = ($q['type'] === 'multiple_choice' && !empty($q['options'])) ? json_encode(array_values(array_filter($q['options']))) : null;
        $stmt->execute([':sid' => $surveyId, ':q' => $q['question'], ':type' => $q['type'], ':opts' => $opts, ':order' => $i, ':req' => (int)($q['required'] ?? 1)]);
    }
    return true;
}

function getQuestions(PDO $pdo, int $surveyId): array {
    $stmt = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id=:id ORDER BY sort_order ASC");
    $stmt->execute([':id' => $surveyId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if ($r['options']) $r['options'] = json_decode($r['options'], true);
    }
    return $rows;
}

function submitResponse(PDO $pdo, int $surveyId, int $userId, int $applicationId, array $answers): int {
    $stmt = $pdo->prepare("INSERT INTO survey_responses (survey_id, user_id, application_id) VALUES (:sid, :uid, :aid)");
    $stmt->execute([':sid' => $surveyId, ':uid' => $userId, ':aid' => $applicationId]);
    $responseId = (int)$pdo->lastInsertId();

    $ans = $pdo->prepare("INSERT INTO survey_answers (response_id, question_id, answer) VALUES (:rid, :qid, :ans)");
    foreach ($answers as $qId => $answer) {
        $ans->execute([':rid' => $responseId, ':qid' => (int)$qId, ':ans' => is_array($answer) ? implode(', ', $answer) : $answer]);
    }
    return $responseId;
}

function hasResponded(PDO $pdo, int $surveyId, int $userId): bool {
    $stmt = $pdo->prepare("SELECT id FROM survey_responses WHERE survey_id=:sid AND user_id=:uid LIMIT 1");
    $stmt->execute([':sid' => $surveyId, ':uid' => $userId]);
    return (bool)$stmt->fetch();
}

function getResponses(PDO $pdo, int $surveyId): array {
    $stmt = $pdo->prepare("
        SELECT sr.*, u.first_name, u.last_name
        FROM survey_responses sr
        JOIN users u ON sr.user_id = u.id
        WHERE sr.survey_id = :sid
        ORDER BY sr.submitted_at DESC
    ");
    $stmt->execute([':sid' => $surveyId]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($responses as &$r) {
        $aStmt = $pdo->prepare("SELECT sa.*, sq.question, sq.type FROM survey_answers sa JOIN survey_questions sq ON sa.question_id = sq.id WHERE sa.response_id = :rid ORDER BY sq.sort_order");
        $aStmt->execute([':rid' => $r['id']]);
        $r['answers'] = $aStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $responses;
}

function getSurveyAnalytics(PDO $pdo, int $surveyId): array {
    $questions = getQuestions($pdo, $surveyId);
    $analytics = [];
    foreach ($questions as $q) {
        $stmt = $pdo->prepare("SELECT sa.answer FROM survey_answers sa JOIN survey_responses sr ON sa.response_id = sr.id WHERE sa.question_id = :qid AND sr.survey_id = :sid");
        $stmt->execute([':qid' => $q['id'], ':sid' => $surveyId]);
        $answers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $data = ['question' => $q['question'], 'type' => $q['type'], 'answers' => $answers];
        if ($q['type'] === 'rating_scale') {
            $nums = array_filter(array_map('intval', $answers));
            $data['average'] = count($nums) > 0 ? round(array_sum($nums) / count($nums), 1) : 0;
        } elseif ($q['type'] === 'multiple_choice') {
            $data['counts'] = array_count_values($answers);
        }
        $analytics[] = $data;
    }
    return $analytics;
}

function getActiveSurveysForStudent(PDO $pdo, int $userId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.*, sch.title AS scholarship_title,
                   (SELECT id FROM survey_responses sr WHERE sr.survey_id = s.id AND sr.user_id = :uid2 LIMIT 1) AS responded_id
            FROM surveys s
            LEFT JOIN scholarships sch ON s.scholarship_id = sch.id
            WHERE s.status = 'active'
              AND (
                s.scholarship_id IS NULL
                OR s.scholarship_id IN (
                  SELECT scholarship_id FROM applications WHERE user_id = :uid AND status IN ('approved','completed')
                )
              )
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getEligibleApplicationForSurvey(PDO $pdo, int $userId, int $surveyId): ?array {
    $survey = getSurveyById($pdo, $surveyId);
    if (!$survey) return null;

    $where = $survey['scholarship_id'] ? 'AND a.scholarship_id = :sch' : '';
    $params = [':uid' => $userId];
    if ($survey['scholarship_id']) $params[':sch'] = $survey['scholarship_id'];

    $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = :uid AND status IN ('approved','completed') $where LIMIT 1");
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
