-- ============================================================
-- KATHAKALI BRIDGE — MySQL Schema
-- Run this once on your MySQL server:
--   mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS kathakali_bridge
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE kathakali_bridge;

-- ============================================================
-- TEACHERS
-- moodle_user_id  = the numeric user id from Moodle's mdl_user
-- moodle_token    = Moodle web-service token for REST calls
--                   (generate in Moodle → Site admin → Web services → Manage tokens)
-- ============================================================
CREATE TABLE IF NOT EXISTS teachers (
    teacher_id      INT          PRIMARY KEY AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    specialty       VARCHAR(120) DEFAULT 'Instructor',
    email           VARCHAR(160) UNIQUE NOT NULL,
    initials        CHAR(4)      DEFAULT 'T',
    moodle_user_id  INT          DEFAULT NULL,   -- links to Moodle mdl_user.id
    moodle_token    VARCHAR(32)  DEFAULT NULL,   -- web-service token
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- CLASSES
-- moodle_course_id  = mdl_course.id in Moodle (0 = not linked yet)
-- status            = 'completed' | 'active' | 'upcoming'
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
    class_id        INT          PRIMARY KEY AUTO_INCREMENT,
    teacher_id      INT          NOT NULL,
    name            VARCHAR(200) NOT NULL,
    description     TEXT,
    class_date      DATE         NOT NULL,
    class_time      TIME         NOT NULL,
    duration_min    INT          DEFAULT 90,
    status          ENUM('completed','active','upcoming','cancelled') DEFAULT 'upcoming',
    moodle_course_id INT         DEFAULT 0,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
);

-- ============================================================
-- STUDENTS
-- moodle_user_id  = numeric id in Moodle (0 = not linked)
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    student_id      INT          PRIMARY KEY AUTO_INCREMENT,
    label           VARCHAR(40)  NOT NULL,        -- e.g. "Student-1"
    email           VARCHAR(160) UNIQUE,
    class_id        INT          NOT NULL,
    moodle_user_id  INT          DEFAULT 0,
    accuracy_pct    TINYINT      DEFAULT 0,        -- 0-100
    attendance_pct  TINYINT      DEFAULT 0,        -- 0-100
    submission_count SMALLINT    DEFAULT 0,
    status          ENUM('active','inactive') DEFAULT 'active',
    joined_date     DATE,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE
);

-- ============================================================
-- ASSIGNMENTS  (critique queue)
-- moodle_assign_id = mdl_assign.id in Moodle (0 = local only)
-- diff_score       = percentage deviation from reference (0-100)
-- ============================================================
CREATE TABLE IF NOT EXISTS assignments (
    assignment_id   INT          PRIMARY KEY AUTO_INCREMENT,
    student_id      INT          NOT NULL,
    teacher_id      INT          NOT NULL,
    class_id        INT          NOT NULL,
    title           VARCHAR(200) NOT NULL,
    ref_image       VARCHAR(300) DEFAULT NULL,     -- path to reference image
    sub_image       VARCHAR(300) DEFAULT NULL,     -- path to student submission
    ref_label       VARCHAR(120) DEFAULT 'Teacher Reference',
    sub_label       VARCHAR(120) DEFAULT 'Student Submission',
    diff_score      TINYINT      DEFAULT 0,        -- AI deviation %
    moodle_assign_id INT         DEFAULT 0,
    review_status   ENUM('pending','reviewed','revision') DEFAULT 'pending',
    submitted_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES students(student_id)  ON DELETE CASCADE,
    FOREIGN KEY (teacher_id)  REFERENCES teachers(teacher_id)  ON DELETE CASCADE,
    FOREIGN KEY (class_id)    REFERENCES classes(class_id)     ON DELETE CASCADE
);

-- ============================================================
-- NOTIFICATIONS
-- Populated by PHP when: submission arrives, class starts soon, etc.
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    notif_id    INT          PRIMARY KEY AUTO_INCREMENT,
    teacher_id  INT          NOT NULL,
    text        VARCHAR(300) NOT NULL,
    is_unread   TINYINT(1)   DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA  (matches your existing HTML mock data)
-- Run ONLY on first setup; skip if data already exists.
-- ============================================================

INSERT IGNORE INTO teachers (teacher_id, name, specialty, email, initials)
VALUES (1, 'Teacher Name', 'Kathakali Instructor', 'teacher@kathakalibridge.in', 'TN');

INSERT IGNORE INTO classes
    (class_id, teacher_id, name, class_date, class_time, duration_min, status, moodle_course_id)
VALUES
    (1, 1, 'Class 1 — Navarasas',         CURDATE(), '10:00:00', 90, 'completed', 101),
    (2, 1, 'Class 2 — Mudra Basics',      CURDATE(), '14:00:00', 90, 'active',    102),
    (3, 1, 'Class 3 — Thandava Movements',CURDATE(), '17:30:00', 90, 'upcoming',  103),
    (4, 1, 'Class 4 — Advanced Expressions',CURDATE(),'19:00:00',90, 'upcoming',  104);

INSERT IGNORE INTO students
    (student_id, label, class_id, accuracy_pct, attendance_pct, submission_count, status, joined_date)
VALUES
    (1,'Student-1',2,87,94,12,'active','2024-03-01'),
    (2,'Student-2',2,74,88, 9,'active','2024-03-01'),
    (3,'Student-3',1,91,100,14,'active','2024-01-15'),
    (4,'Student-4',3,62,76, 7,'active','2024-06-01'),
    (5,'Student-5',1,55,60, 4,'inactive','2024-02-01'),
    (6,'Student-6',2,79,83,11,'active','2024-04-01'),
    (7,'Student-7',3,83,90,10,'active','2024-03-15');

INSERT IGNORE INTO assignments
    (assignment_id, student_id, teacher_id, class_id, title, diff_score,
     ref_label, sub_label, moodle_assign_id, submitted_at)
VALUES
    (1,1,1,2,'Hamsasya Mudra — Attempt 3',38,
     'Teacher Reference — Session 5','Student-1 Submission',201,DATE_SUB(NOW(),INTERVAL 2 HOUR)),
    (2,2,1,2,'Tripataka — Finger Alignment',21,
     'Teacher Reference — Session 4','Student-2 Submission',202,DATE_SUB(NOW(),INTERVAL 5 HOUR)),
    (3,3,1,1,'Ardhachandra — Wrist Angle',9,
     'Teacher Reference — Session 8','Student-3 Submission',203,DATE_SUB(NOW(),INTERVAL 1 DAY)),
    (4,4,1,3,'Pataka — Full Sequence',15,
     'Teacher Reference — Session 6','Student-4 Submission',204,DATE_SUB(NOW(),INTERVAL 1 DAY));

INSERT IGNORE INTO notifications (teacher_id, text, is_unread, created_at)
VALUES
    (1,'Student-1 submitted a mudra practice video',1,DATE_SUB(NOW(),INTERVAL 5 MINUTE)),
    (1,'Class 2 starts in 45 minutes',             1,DATE_SUB(NOW(),INTERVAL 12 MINUTE)),
    (1,'Student-2 sent a message about today\'s class',1,DATE_SUB(NOW(),INTERVAL 30 MINUTE)),
    (1,'Student-3 completed all assignments this week',0,DATE_SUB(NOW(),INTERVAL 1 HOUR)),
    (1,'3 new assignment submissions are pending review',0,DATE_SUB(NOW(),INTERVAL 2 HOUR)),
    (1,'Student-4 accuracy improved to 92% this week',0,DATE_SUB(NOW(),INTERVAL 1 DAY)),
    (1,'Class 4 schedule confirmed for 7:00 PM',   0,DATE_SUB(NOW(),INTERVAL 1 DAY));

