-- ============================================================
-- KATHAKALI BRIDGE — Sync Schema additions
-- Compatible with older MySQL/MariaDB versions
-- Run once:
-- mysql -u root -p kathakali_bridge < schema_sync.sql
-- ============================================================

USE kathakali_bridge;

-- Add moodle_event_id to classes
ALTER TABLE classes
    ADD COLUMN moodle_event_id INT DEFAULT 0;

-- Add extra columns to teachers
ALTER TABLE teachers
    ADD COLUMN phone VARCHAR(30) DEFAULT NULL,
    ADD COLUMN dob DATE DEFAULT NULL,
    ADD COLUMN gender VARCHAR(20) DEFAULT NULL,
    ADD COLUMN location VARCHAR(160) DEFAULT NULL,
    ADD COLUMN timezone VARCHAR(60) DEFAULT 'Asia/Kolkata',
    ADD COLUMN avatar_path VARCHAR(300) DEFAULT NULL,
    ADD COLUMN art_category VARCHAR(120) DEFAULT NULL,
    ADD COLUMN art_form VARCHAR(120) DEFAULT NULL,
    ADD COLUMN years_experience VARCHAR(30) DEFAULT NULL,
    ADD COLUMN awards TEXT DEFAULT NULL,
    ADD COLUMN qualification VARCHAR(80) DEFAULT NULL,
    ADD COLUMN institution VARCHAR(160) DEFAULT NULL,
    ADD COLUMN cert_path VARCHAR(300) DEFAULT NULL,
    ADD COLUMN student_levels VARCHAR(100) DEFAULT NULL,
    ADD COLUMN languages VARCHAR(160) DEFAULT NULL,
    ADD COLUMN age_group_pref VARCHAR(40) DEFAULT NULL,
    ADD COLUMN instagram VARCHAR(120) DEFAULT NULL,
    ADD COLUMN linkedin VARCHAR(200) DEFAULT NULL,
    ADD COLUMN youtube VARCHAR(200) DEFAULT NULL,
    ADD COLUMN portfolio_url VARCHAR(200) DEFAULT NULL,
    ADD COLUMN bio TEXT DEFAULT NULL,
    ADD COLUMN equipment_needed VARCHAR(200) DEFAULT NULL,
    ADD COLUMN is_approved TINYINT(1) DEFAULT 0;

-- Portfolio images table
CREATE TABLE IF NOT EXISTS portfolio_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    file_path VARCHAR(300) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
);

-- Sync audit log
CREATE TABLE IF NOT EXISTS sync_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(60) NOT NULL,
    status VARCHAR(10) NOT NULL,
    detail TEXT,
    ref_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add feedback column to assignments
ALTER TABLE assignments
    ADD COLUMN feedback TEXT DEFAULT NULL;