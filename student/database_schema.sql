-- Digital Art School - Student Module Database Schema
-- Database: das_student
-- Collation: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS das_student CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE das_student;

-- Students Table
CREATE TABLE IF NOT EXISTS students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    age INT,
    location VARCHAR(255),
    
    -- Art Selection & Interests
    art_category VARCHAR(100),
    art_discipline VARCHAR(100),
    secondary_interest TEXT,
    
    -- Experience & Background
    skill_level ENUM('complete_beginner', 'basic', 'intermediate', 'advanced'),
    years_of_experience DECIMAL(4,1),
    previous_training TEXT,
    showcase_file VARCHAR(500),
    
    -- Learning Goals & Schedule
    learning_purpose ENUM('hobby', 'professional', 'performance', 'certification', 'therapy'),
    time_commitment VARCHAR(50),
    preferred_schedule VARCHAR(100),
    specific_goals TEXT,
    
    -- Health & Accessibility (Optional)
    physical_constraints TEXT,
    learning_accommodations TEXT,
    
    -- System Fields
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Moodle Integration Fields
    moodle_user_id INT NULL,
    moodle_username VARCHAR(100) NULL,
    moodle_synced_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_art_category (art_category),
    INDEX idx_skill_level (skill_level),
    INDEX idx_moodle_user (moodle_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teachers Table (Reference for teacher selection)
CREATE TABLE IF NOT EXISTS teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    specialization VARCHAR(100),
    art_disciplines TEXT,
    experience_years INT,
    bio TEXT,
    profile_image VARCHAR(500),
    available_slots TEXT,
    max_students INT DEFAULT 20,
    current_students INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_specialization (specialization)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher Requests Table
CREATE TABLE IF NOT EXISTS teacher_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    request_message TEXT,
    request_status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_date TIMESTAMP NULL,
    
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    INDEX idx_status (request_status),
    INDEX idx_student (student_id),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Sample Teachers Data
INSERT INTO teachers (teacher_name, email, specialization, art_disciplines, experience_years, bio, available_slots, max_students) VALUES
('Dr. Lakshmi Nair', 'lakshmi.nair@digitalartschool.in', 'Classical Dance', 'Kathakali, Mohiniyattam', 15, 'Renowned performer and teacher with expertise in Kerala classical dance forms', 'weekday_evening,weekend_morning', 20),
('Prof. Ravi Kumar', 'ravi.kumar@digitalartschool.in', 'Vocal Music', 'Carnatic Vocal, Kathakali Sangeetham', 20, 'Celebrated vocalist specializing in Carnatic music and traditional Kerala music forms', 'weekday_morning,weekend_evening', 15),
('Smt. Priya Menon', 'priya.menon@digitalartschool.in', 'Visual Arts', 'Mural Painting, Traditional Art', 12, 'Expert in Kerala mural painting techniques and traditional art forms', 'weekday_evening,weekend_all_day', 18),
('Sri. Anand Krishnan', 'anand.krishnan@digitalartschool.in', 'Classical Dance', 'Mohiniyattam, Bharatanatyam', 10, 'Young and dynamic teacher with fresh approaches to classical dance', 'weekday_evening,weekend_morning', 25),
('Smt. Geetha Sharma', 'geetha.sharma@digitalartschool.in', 'Vocal Music', 'Hindustani Classical, Light Music', 18, 'Versatile musician with expertise in both classical and contemporary music', 'weekday_all_day,weekend_morning', 20);

-- Sessions Log Table (for tracking login sessions)
CREATE TABLE IF NOT EXISTS session_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_login (student_id, login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Moodle Sync Log Table
CREATE TABLE IF NOT EXISTS moodle_sync_log (
    sync_id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    moodle_response TEXT,
    sync_status ENUM('success', 'failed') NOT NULL,
    error_message TEXT NULL,
    sync_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status (sync_status),
    INDEX idx_timestamp (sync_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
