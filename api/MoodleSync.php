<?php
/**
 * KATHAKALI BRIDGE — MoodleSync
 * =====================================================
 * Handles all real-time sync from kathakali_bridge DB
 * to Moodle via REST web services.
 *
 * Usage:
 *   require_once 'api/MoodleSync.php';
 *   $sync = new MoodleSync();
 *
 *   $sync->createTeacher($teacherData);
 *   $sync->createStudent($studentData, $courseId);
 *   $sync->enrollStudent($moodleUserId, $moodleCourseId);
 *   $sync->syncAssignment($assignmentData);
 *   $sync->syncClassEvent($classData);
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

class MoodleSync {

    private PDO $db;

    public function __construct() {
        $this->db = DB::conn();
    }

    // =========================================================
    // CORE: Call Moodle REST API
    // =========================================================
    private function call(string $function, array $params = []): array {
        $params['wstoken']            = MOODLE_TOKEN;
        $params['wsfunction']         = $function;
        $params['moodlewsrestformat'] = 'json';

        $ch = curl_init(MOODLE_REST_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err)          return ['_error' => 'cURL: ' . $err];
        $data = json_decode($raw, true);
        if ($data === null) return ['_error' => 'Invalid JSON: ' . substr($raw, 0, 300)];
        if (isset($data['exception'])) return ['_error' => $data['message'] ?? $data['exception']];
        return $data;
    }

    // =========================================================
    // HELPER: Log sync events to DB for audit trail
    // =========================================================
    private function log(string $action, string $status, string $detail = '', ?int $refId = null): void {
        try {
            $this->db->prepare("
                INSERT INTO sync_log (action, status, detail, ref_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$action, $status, $detail, $refId]);
        } catch (PDOException $e) {
            // Table may not exist yet — silently skip
        }
    }

    // =========================================================
    // 1. CREATE TEACHER → Moodle user account
    //
    // Called from: register.php after successful DB insert
    // What it does:
    //   - Creates a Moodle user via core_user_create_users
    //   - Stores moodle_user_id back in teachers table
    //   - Assigns the teacher role at system level
    // =========================================================
    public function createTeacher(array $teacher): array {
        $teacherId = (int)$teacher['teacher_id'];

        // Build a safe Moodle username from email
        $username = strtolower(preg_replace('/[^a-z0-9._-]/i', '', explode('@', $teacher['email'])[0]))
                    . '_t' . $teacherId;

        // Temporary password — teacher will be emailed to reset
        $tempPassword = 'KBridge@' . rand(1000, 9999);

        $result = $this->call('core_user_create_users', [
            'users[0][username]'    => $username,
            'users[0][password]'    => $tempPassword,
            'users[0][firstname]'   => explode(' ', $teacher['name'])[0],
            'users[0][lastname]'    => implode(' ', array_slice(explode(' ', $teacher['name']), 1)) ?: $teacher['name'],
            'users[0][email]'       => $teacher['email'],
            'users[0][auth]'        => 'manual',
            'users[0][timezone]'    => $teacher['timezone'] ?? 'Asia/Kolkata',
            'users[0][description]' => $teacher['bio'] ?? '',
            'users[0][city]'        => $teacher['location'] ?? '',
            'users[0][country]'     => 'IN',
            'users[0][customfields][0][type]'  => 'art_form',
            'users[0][customfields][0][value]' => $teacher['art_form'] ?? '',
        ]);

        if (isset($result['_error'])) {
            $this->log('create_teacher', 'error', $result['_error'], $teacherId);
            return ['ok' => false, 'error' => $result['_error']];
        }

        $moodleUserId = (int)($result[0]['id'] ?? 0);
        if (!$moodleUserId) {
            $this->log('create_teacher', 'error', 'No user ID returned', $teacherId);
            return ['ok' => false, 'error' => 'Moodle did not return a user ID'];
        }

        // Store moodle_user_id back in kathakali_bridge DB
        $this->db->prepare("UPDATE teachers SET moodle_user_id = ? WHERE teacher_id = ?")
                 ->execute([$moodleUserId, $teacherId]);

        $this->log('create_teacher', 'ok', "Moodle user {$moodleUserId} created for teacher {$teacherId}", $teacherId);

        return [
            'ok'             => true,
            'moodle_user_id' => $moodleUserId,
            'temp_password'  => $tempPassword,
            'username'       => $username,
        ];
    }

    // =========================================================
    // 2. CREATE STUDENT → Moodle user account
    //
    // Called from: api/students.php when a new student is added
    // What it does:
    //   - Creates a Moodle user for the student
    //   - Stores moodle_user_id in students table
    //   - Automatically enrolls them in the course
    // =========================================================
    public function createStudent(array $student, int $moodleCourseId): array {
        $studentId = (int)$student['student_id'];

        $nameParts = explode(' ', $student['label'] ?? 'Student');
        $username  = strtolower(preg_replace('/[^a-z0-9]/i', '', $student['label']))
                     . '_s' . $studentId;
        $email     = $student['email'] ?? ($username . '@kathakalibridge.student');
        $tempPassword = 'KBStudent@' . rand(1000, 9999);

        $result = $this->call('core_user_create_users', [
            'users[0][username]'  => $username,
            'users[0][password]'  => $tempPassword,
            'users[0][firstname]' => $nameParts[0],
            'users[0][lastname]'  => $nameParts[1] ?? $studentId,
            'users[0][email]'     => $email,
            'users[0][auth]'      => 'manual',
            'users[0][timezone]'  => 'Asia/Kolkata',
            'users[0][city]'      => '',
            'users[0][country]'   => 'IN',
        ]);

        if (isset($result['_error'])) {
            $this->log('create_student', 'error', $result['_error'], $studentId);
            return ['ok' => false, 'error' => $result['_error']];
        }

        $moodleUserId = (int)($result[0]['id'] ?? 0);
        if (!$moodleUserId) {
            return ['ok' => false, 'error' => 'No user ID returned from Moodle'];
        }

        // Store moodle_user_id in kathakali_bridge.students
        $this->db->prepare("UPDATE students SET moodle_user_id = ? WHERE student_id = ?")
                 ->execute([$moodleUserId, $studentId]);

        $this->log('create_student', 'ok', "Moodle user {$moodleUserId} for student {$studentId}", $studentId);

        // Immediately enroll into the Moodle course
        $enroll = $this->enrollStudent($moodleUserId, $moodleCourseId);

        return [
            'ok'             => true,
            'moodle_user_id' => $moodleUserId,
            'enrolled'       => $enroll['ok'] ?? false,
            'temp_password'  => $tempPassword,
            'username'       => $username,
        ];
    }

    // =========================================================
    // 3. ENROLL STUDENT → Moodle course enrollment
    //
    // Called from: createStudent() automatically
    //              or api/students.php when class changes
    // Role ID 5 = student in Moodle default
    // =========================================================
    public function enrollStudent(int $moodleUserId, int $moodleCourseId, int $roleId = 5): array {
        if (!$moodleUserId || !$moodleCourseId) {
            return ['ok' => false, 'error' => 'Missing moodle_user_id or moodle_course_id'];
        }

        $result = $this->call('enrol_manual_enrol_users', [
            'enrolments[0][roleid]'   => $roleId,
            'enrolments[0][userid]'   => $moodleUserId,
            'enrolments[0][courseid]' => $moodleCourseId,
        ]);

        // enrol_manual_enrol_users returns null on success
        if (isset($result['_error'])) {
            $this->log('enroll_student', 'error', $result['_error']);
            return ['ok' => false, 'error' => $result['_error']];
        }

        $this->log('enroll_student', 'ok', "User {$moodleUserId} enrolled in course {$moodleCourseId}");
        return ['ok' => true];
    }

    // =========================================================
    // 4. SYNC ASSIGNMENT → Moodle assignment + submission grade
    //
    // Called from: api/assignments.php when teacher sends feedback
    // What it does:
    //   - Looks up the Moodle assignment ID
    //   - Posts the teacher's grade/feedback via
    //     mod_assign_save_grade
    // =========================================================
    public function syncAssignment(array $assignment): array {
        $assignId      = (int)$assignment['assignment_id'];
        $moodleAssignId = (int)($assignment['moodle_assign_id'] ?? 0);
        $moodleUserId  = (int)($assignment['moodle_user_id'] ?? 0);
        $feedback      = $assignment['feedback'] ?? '';
        $diffScore     = (int)($assignment['diff_score'] ?? 0);

        // Convert deviation % to a Moodle grade (100 - deviation)
        $grade = max(0, 100 - $diffScore);

        if (!$moodleAssignId || !$moodleUserId) {
            // Try to resolve moodle IDs from DB
            $row = $this->db->prepare("
                SELECT a.moodle_assign_id, s.moodle_user_id
                FROM   assignments a
                JOIN   students s ON s.student_id = a.student_id
                WHERE  a.assignment_id = ?
            ");
            $row->execute([$assignId]);
            $resolved = $row->fetch();
            if ($resolved) {
                $moodleAssignId = (int)$resolved['moodle_assign_id'];
                $moodleUserId   = (int)$resolved['moodle_user_id'];
            }
        }

        if (!$moodleAssignId || !$moodleUserId) {
            $this->log('sync_assignment', 'skip', 'No Moodle IDs — cannot sync', $assignId);
            return ['ok' => false, 'error' => 'Assignment or student not linked to Moodle yet'];
        }

        $result = $this->call('mod_assign_save_grade', [
            'assignmentid'                       => $moodleAssignId,
            'userid'                             => $moodleUserId,
            'grade'                              => $grade,
            'attemptnumber'                      => -1,
            'addattempt'                         => 0,
            'workflowstate'                      => 'released',
            'applytoall'                         => 0,
            'plugindata[assignfeedbackcomments_editor][text]'   => $feedback,
            'plugindata[assignfeedbackcomments_editor][format]' => 1,
        ]);

        if (isset($result['_error'])) {
            $this->log('sync_assignment', 'error', $result['_error'], $assignId);
            return ['ok' => false, 'error' => $result['_error']];
        }

        // Mark as reviewed in local DB
        $this->db->prepare("UPDATE assignments SET review_status='reviewed' WHERE assignment_id=?")
                 ->execute([$assignId]);

        $this->log('sync_assignment', 'ok', "Grade {$grade} synced for assignment {$assignId}", $assignId);
        return ['ok' => true, 'grade_posted' => $grade];
    }

    // =========================================================
    // 5. SYNC CLASS SCHEDULE → Moodle calendar event
    //
    // Called from: when a class is created or updated
    // What it does:
    //   - Creates a Moodle course calendar event
    //   - Stores moodle_event_id back in classes table
    // =========================================================
    public function syncClassEvent(array $class): array {
        $classId   = (int)$class['class_id'];
        $courseId  = (int)$class['moodle_course_id'];
        $name      = $class['name'] ?? 'Class';
        $date      = $class['class_date'] ?? date('Y-m-d');
        $time      = $class['class_time'] ?? '09:00:00';
        $duration  = (int)($class['duration_min'] ?? 90);

        if (!$courseId) {
            return ['ok' => false, 'error' => 'No moodle_course_id set for this class'];
        }

        // Convert date+time to Unix timestamp
        $timestamp = strtotime($date . ' ' . $time);

        $result = $this->call('core_calendar_create_calendar_events', [
            'events[0][name]'         => $name,
            'events[0][description]'  => 'Kathakali Bridge live session: ' . $name,
            'events[0][format]'       => 1,
            'events[0][courseid]'     => $courseId,
            'events[0][groupid]'      => 0,
            'events[0][userid]'       => 0,
            'events[0][type]'         => 1,     // 1 = course event
            'events[0][timestart]'    => $timestamp,
            'events[0][timeduration]' => $duration * 60,
            'events[0][visible]'      => 1,
        ]);

        if (isset($result['_error'])) {
            $this->log('sync_class_event', 'error', $result['_error'], $classId);
            return ['ok' => false, 'error' => $result['_error']];
        }

        $moodleEventId = (int)($result['events'][0]['id'] ?? 0);

        // Store moodle_event_id in classes table
        if ($moodleEventId) {
            try {
                $this->db->prepare("UPDATE classes SET moodle_event_id = ? WHERE class_id = ?")
                         ->execute([$moodleEventId, $classId]);
            } catch (PDOException $e) {
                // Column may not exist yet — add it via schema update below
            }
        }

        $this->log('sync_class_event', 'ok', "Event {$moodleEventId} created for class {$classId}", $classId);
        return ['ok' => true, 'moodle_event_id' => $moodleEventId];
    }

    // =========================================================
    // 6. BULK SYNC — sync all unsynced records
    //
    // Called from: admin panel or cron job
    // =========================================================
    public function bulkSync(): array {
        $results = ['teachers' => [], 'students' => [], 'events' => []];

        // Unsynced teachers
        $teachers = $this->db->query(
            "SELECT * FROM teachers WHERE moodle_user_id IS NULL OR moodle_user_id = 0"
        )->fetchAll();
        foreach ($teachers as $t) {
            $results['teachers'][] = $this->createTeacher($t);
        }

        // Unsynced students — need their course's moodle_course_id
        $students = $this->db->query("
            SELECT s.*, c.moodle_course_id
            FROM   students s
            JOIN   classes c ON c.class_id = s.class_id
            WHERE  (s.moodle_user_id IS NULL OR s.moodle_user_id = 0)
            AND    c.moodle_course_id > 0
        ")->fetchAll();
        foreach ($students as $s) {
            $results['students'][] = $this->createStudent($s, (int)$s['moodle_course_id']);
        }

        // Unsynced class events
        $classes = $this->db->query("
            SELECT * FROM classes
            WHERE moodle_course_id > 0
            AND  (moodle_event_id IS NULL OR moodle_event_id = 0)
        ")->fetchAll();
        foreach ($classes as $c) {
            $results['events'][] = $this->syncClassEvent($c);
        }

        return $results;
    }

    // =========================================================
    // TEST: Verify Moodle connection and list available functions
    // =========================================================
    public function testConnection(): array {
        $info = $this->call('core_webservice_get_site_info');
        if (isset($info['_error'])) {
            return ['ok' => false, 'error' => $info['_error']];
        }
        return [
            'ok'         => true,
            'site'       => $info['sitename']  ?? '—',
            'version'    => $info['release']   ?? '—',
            'token_user' => $info['username']  ?? '—',
            'functions'  => array_column($info['functions'] ?? [], 'name'),
        ];
    }
}