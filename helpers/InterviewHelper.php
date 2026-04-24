<?php
/**
 * Interview System Helper
 * Handles auto-assignment of applicants to interview groups
 */

class InterviewHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function autoAssignApplicants($scholarshipId, $sessionDate) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare('
                SELECT a.id, a.user_id, a.created_at
                FROM applications a
                LEFT JOIN interview_assignments ia ON a.id = ia.application_id
                WHERE a.scholarship_id = :sid
                AND a.status = "approved"
                AND ia.id IS NULL
                AND a.user_id NOT IN (
                    SELECT a2.user_id 
                    FROM interview_assignments ia2
                    JOIN applications a2 ON ia2.application_id = a2.id
                    WHERE a2.scholarship_id = :sid2
                )
                ORDER BY a.created_at ASC, a.id ASC
            ');
            $stmt->execute([':sid' => $scholarshipId, ':sid2' => $scholarshipId]);
            $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($applicants)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'No approved applicants to assign.'];
            }
            
            $sessions = $this->createSessionsForDate($scholarshipId, $sessionDate);
            
            $groups = [];
            foreach ($sessions as $session) {
                $groupsStmt = $this->pdo->prepare('
                    SELECT id, session_id, group_code, max_capacity, current_count
                    FROM interview_groups
                    WHERE session_id = :sid
                    ORDER BY group_code ASC
                ');
                $groupsStmt->execute([':sid' => $session['id']]);
                $groups = array_merge($groups, $groupsStmt->fetchAll(PDO::FETCH_ASSOC));
            }
            
            if (empty($groups)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'No interview groups available.'];
            }
            
            $assignedCount = 0;
            $groupIndex    = 0;
            
            foreach ($applicants as $applicant) {
                while ($groupIndex < count($groups)) {
                    $group = $groups[$groupIndex];
                    if ($group['current_count'] < $group['max_capacity']) {
                        $assignStmt = $this->pdo->prepare('
                            INSERT INTO interview_assignments 
                            (application_id, group_id, assigned_at)
                            VALUES (:app_id, :group_id, NOW())
                        ');
                        $assignStmt->execute([
                            ':app_id'   => $applicant['id'],
                            ':group_id' => $group['id'],
                        ]);
                        
                        $this->pdo->prepare('UPDATE interview_groups SET current_count = current_count + 1 WHERE id = :id')
                            ->execute([':id' => $group['id']]);
                        
                        $groups[$groupIndex]['current_count']++;
                        $assignedCount++;
                        break;
                    }
                    $groupIndex++;
                }
                if ($groupIndex >= count($groups)) break;
            }
            
            $this->pdo->commit();
            return [
                'success'          => true,
                'message'          => "Successfully assigned {$assignedCount} applicant(s) to interview groups.",
                'assigned_count'   => $assignedCount,
                'total_applicants' => count($applicants)
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('[InterviewHelper] autoAssignApplicants error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to assign applicants: ' . $e->getMessage()];
        }
    }
    
    private function createSessionsForDate($scholarshipId, $sessionDate) {
        $sessions   = [];
        $timeBlocks = [
            'AM' => ['start' => '08:00:00', 'end' => '11:30:00'],
            'PM' => ['start' => '13:00:00', 'end' => '16:00:00'],
        ];
        
        foreach ($timeBlocks as $block => $times) {
            // Use correct column names: time_block, time_start, time_end
            $checkStmt = $this->pdo->prepare('
                SELECT id FROM interview_sessions
                WHERE session_date = :date AND time_block = :block AND scholarship_id = :sid
            ');
            $checkStmt->execute([':date' => $sessionDate, ':block' => $block, ':sid' => $scholarshipId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $sessionId = $existing['id'];
            } else {
                $createStmt = $this->pdo->prepare('
                    INSERT INTO interview_sessions 
                    (scholarship_id, session_date, time_block, time_start, time_end)
                    VALUES (:sid, :date, :block, :start, :end)
                ');
                $createStmt->execute([
                    ':sid'   => $scholarshipId,
                    ':date'  => $sessionDate,
                    ':block' => $block,
                    ':start' => $times['start'],
                    ':end'   => $times['end'],
                ]);
                $sessionId = $this->pdo->lastInsertId();
            }
            
            $sessions[] = ['id' => $sessionId, 'time_block' => $block];
            
            // Create groups for this session
            $groupCodes = $block === 'AM' ? ['A1', 'A2'] : ['B1', 'B2'];
            foreach ($groupCodes as $code) {
                $checkGroupStmt = $this->pdo->prepare('
                    SELECT id FROM interview_groups WHERE session_id = :sid AND group_code = :code
                ');
                $checkGroupStmt->execute([':sid' => $sessionId, ':code' => $code]);
                if (!$checkGroupStmt->fetch()) {
                    $this->pdo->prepare('
                        INSERT INTO interview_groups (session_id, group_code, max_capacity, current_count)
                        VALUES (:sid, :code, 10, 0)
                    ')->execute([':sid' => $sessionId, ':code' => $code]);
                }
            }
        }
        
        return $sessions;
    }
    
    public function getInterviewSchedule($scholarshipId) {
        $stmt = $this->pdo->prepare('
            SELECT 
                s.id as session_id,
                s.session_date,
                s.time_block,
                s.time_start,
                s.time_end,
                s.status as session_status,
                g.id as group_id,
                g.group_code,
                g.max_capacity,
                g.current_count
            FROM interview_sessions s
            LEFT JOIN interview_groups g ON s.id = g.session_id
            WHERE s.scholarship_id = :sid
            ORDER BY s.session_date ASC, s.time_block ASC, g.group_code ASC
        ');
        $stmt->execute([':sid' => $scholarshipId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $schedule = [];
        foreach ($results as $row) {
            $key = $row['session_date'] . '_' . $row['time_block'];
            if (!isset($schedule[$key])) {
                $schedule[$key] = [
                    'session_id'     => $row['session_id'],
                    'session_date'   => $row['session_date'],
                    'time_block'     => $row['time_block'],
                    'time_start'     => $row['time_start'],
                    'time_end'       => $row['time_end'],
                    'session_status' => $row['session_status'],
                    'groups'         => [],
                ];
            }
            if ($row['group_id']) {
                $schedule[$key]['groups'][] = [
                    'group_id'     => $row['group_id'],
                    'group_code'   => $row['group_code'],
                    'max_capacity' => $row['max_capacity'],
                    'current_count'=> $row['current_count'],
                ];
            }
        }
        return array_values($schedule);
    }
    
    public function getGroupApplicants($groupId) {
        $stmt = $this->pdo->prepare('
            SELECT 
                ia.id as assignment_id,
                ia.attendance_status,
                ia.orientation_status,
                ia.interview_status,
                ia.final_status,
                ia.notes,
                a.id as application_id,
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.student_id
            FROM interview_assignments ia
            JOIN applications a ON ia.application_id = a.id
            JOIN users u ON a.user_id = u.id
            WHERE ia.group_id = :gid
            ORDER BY ia.id ASC
        ');
        $stmt->execute([':gid' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateApplicantProgress($assignmentId, $updates) {
        try {
            $fields = [];
            $params = [':id' => $assignmentId];
            $allowed = ['attendance_status', 'orientation_status', 'interview_status', 'final_status', 'notes'];
            
            foreach ($updates as $field => $value) {
                if (in_array($field, $allowed)) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            
            if (empty($fields)) return false;
            
            $sql = 'UPDATE interview_assignments SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $this->pdo->prepare($sql)->execute($params);
            return true;
        } catch (Exception $e) {
            error_log('[InterviewHelper] updateApplicantProgress error: ' . $e->getMessage());
            return false;
        }
    }
}
