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
    
    /**
     * Auto-assign approved applicants to interview groups
     * @param int $scholarshipId
     * @param string $sessionDate (Y-m-d format)
     * @return array Result with success status and message
     */
    public function autoAssignApplicants($scholarshipId, $sessionDate) {
        try {
            $this->pdo->beginTransaction();
            
            // Get approved applicants who are not yet assigned
            $stmt = $this->pdo->prepare('
                SELECT a.id, a.user_id, a.created_at
                FROM applications a
                LEFT JOIN interview_assignments ia ON a.id = ia.application_id
                WHERE a.scholarship_id = :sid
                AND a.status = "approved"
                AND ia.id IS NULL
                ORDER BY a.created_at ASC, a.id ASC
            ');
            $stmt->execute([':sid' => $scholarshipId]);
            $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($applicants)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'No approved applicants to assign.'];
            }
            
            // Create or get sessions for the date
            $sessions = $this->createSessionsForDate($scholarshipId, $sessionDate);
            
            // Get all groups for these sessions
            $groups = [];
            foreach ($sessions as $session) {
                $groupsStmt = $this->pdo->prepare('
                    SELECT id, group_code, max_capacity, current_count
                    FROM interview_groups
                    WHERE session_id = :sid
                    ORDER BY group_code ASC
                ');
                $groupsStmt->execute([':sid' => $session['id']]);
                $sessionGroups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
                $groups = array_merge($groups, $sessionGroups);
            }
            
            if (empty($groups)) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'No interview groups available.'];
            }
            
            // Assign applicants sequentially to groups
            $assignedCount = 0;
            $groupIndex = 0;
            
            foreach ($applicants as $applicant) {
                // Find next available group
                while ($groupIndex < count($groups)) {
                    $group = $groups[$groupIndex];
                    
                    if ($group['current_count'] < $group['max_capacity']) {
                        // Assign to this group
                        $assignStmt = $this->pdo->prepare('
                            INSERT INTO interview_assignments 
                            (application_id, group_id, assigned_at, locked)
                            VALUES (:app_id, :group_id, NOW(), 1)
                        ');
                        $assignStmt->execute([
                            ':app_id' => $applicant['id'],
                            ':group_id' => $group['id']
                        ]);
                        
                        // Update group count
                        $updateStmt = $this->pdo->prepare('
                            UPDATE interview_groups 
                            SET current_count = current_count + 1
                            WHERE id = :id
                        ');
                        $updateStmt->execute([':id' => $group['id']]);
                        
                        $groups[$groupIndex]['current_count']++;
                        $assignedCount++;
                        break;
                    }
                    
                    $groupIndex++;
                }
                
                // If all groups are full, stop
                if ($groupIndex >= count($groups)) {
                    break;
                }
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "Successfully assigned {$assignedCount} applicant(s) to interview groups.",
                'assigned_count' => $assignedCount,
                'total_applicants' => count($applicants)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('[InterviewHelper] autoAssignApplicants error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to assign applicants: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create sessions and groups for a specific date
     * @param int $scholarshipId
     * @param string $sessionDate
     * @return array Sessions created
     */
    private function createSessionsForDate($scholarshipId, $sessionDate) {
        $sessions = [];
        
        // Define time blocks
        $timeBlocks = [
            'AM' => ['start' => '08:00:00', 'end' => '11:30:00'],
            'PM' => ['start' => '13:00:00', 'end' => '16:00:00']
        ];
        
        foreach ($timeBlocks as $block => $times) {
            // Check if session exists
            $checkStmt = $this->pdo->prepare('
                SELECT id FROM interview_sessions
                WHERE scholarship_id = :sid AND session_date = :date AND time_block = :block
            ');
            $checkStmt->execute([
                ':sid' => $scholarshipId,
                ':date' => $sessionDate,
                ':block' => $block
            ]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $sessionId = $existing['id'];
            } else {
                // Create session
                $createStmt = $this->pdo->prepare('
                    INSERT INTO interview_sessions 
                    (scholarship_id, session_date, time_block, time_start, time_end)
                    VALUES (:sid, :date, :block, :start, :end)
                ');
                $createStmt->execute([
                    ':sid' => $scholarshipId,
                    ':date' => $sessionDate,
                    ':block' => $block,
                    ':start' => $times['start'],
                    ':end' => $times['end']
                ]);
                $sessionId = $this->pdo->lastInsertId();
            }
            
            $sessions[] = ['id' => $sessionId, 'time_block' => $block];
            
            // Create groups for this session
            $groupCodes = $block === 'AM' ? ['A1', 'A2'] : ['B1', 'B2'];
            
            foreach ($groupCodes as $code) {
                // Check if group exists
                $checkGroupStmt = $this->pdo->prepare('
                    SELECT id FROM interview_groups
                    WHERE session_id = :sid AND group_code = :code
                ');
                $checkGroupStmt->execute([':sid' => $sessionId, ':code' => $code]);
                
                if (!$checkGroupStmt->fetch()) {
                    // Create group
                    $createGroupStmt = $this->pdo->prepare('
                        INSERT INTO interview_groups 
                        (session_id, group_code, max_capacity, current_count)
                        VALUES (:sid, :code, 10, 0)
                    ');
                    $createGroupStmt->execute([':sid' => $sessionId, ':code' => $code]);
                }
            }
        }
        
        return $sessions;
    }
    
    /**
     * Get interview schedule for a scholarship
     * @param int $scholarshipId
     * @return array Schedule with sessions, groups, and assignments
     */
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
        
        // Organize by session and group
        $schedule = [];
        foreach ($results as $row) {
            $sessionKey = $row['session_date'] . '_' . $row['time_block'];
            
            if (!isset($schedule[$sessionKey])) {
                $schedule[$sessionKey] = [
                    'session_id' => $row['session_id'],
                    'session_date' => $row['session_date'],
                    'time_block' => $row['time_block'],
                    'time_start' => $row['time_start'],
                    'time_end' => $row['time_end'],
                    'session_status' => $row['session_status'],
                    'groups' => []
                ];
            }
            
            if ($row['group_id']) {
                $schedule[$sessionKey]['groups'][] = [
                    'group_id' => $row['group_id'],
                    'group_code' => $row['group_code'],
                    'max_capacity' => $row['max_capacity'],
                    'current_count' => $row['current_count']
                ];
            }
        }
        
        return array_values($schedule);
    }
    
    /**
     * Get applicants assigned to a specific group
     * @param int $groupId
     * @return array Applicants with their status
     */
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
            ORDER BY ia.assigned_at ASC
        ');
        $stmt->execute([':gid' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update applicant interview progress
     * @param int $assignmentId
     * @param array $updates (attendance_status, orientation_status, interview_status, final_status, notes)
     * @return bool Success
     */
    public function updateApplicantProgress($assignmentId, $updates) {
        try {
            $fields = [];
            $params = [':id' => $assignmentId];
            
            $allowedFields = ['attendance_status', 'orientation_status', 'interview_status', 'final_status', 'notes'];
            
            foreach ($updates as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $fields[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $sql = 'UPDATE interview_assignments SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return true;
        } catch (Exception $e) {
            error_log('[InterviewHelper] updateApplicantProgress error: ' . $e->getMessage());
            return false;
        }
    }
}
