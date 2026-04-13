USE kathakali_bridge;

ALTER TABLE teachers
    ADD COLUMN phone VARCHAR(30) DEFAULT NULL AFTER email,
    ADD COLUMN dob DATE DEFAULT NULL AFTER phone,
    ADD COLUMN gender ENUM('male','female','non-binary','prefer_not') DEFAULT NULL AFTER dob,
    ADD COLUMN location VARCHAR(160) DEFAULT NULL AFTER gender,
    ADD COLUMN timezone VARCHAR(80) DEFAULT 'Asia/Kolkata' AFTER location,
    ADD COLUMN avatar_path VARCHAR(300) DEFAULT NULL AFTER timezone,

    ADD COLUMN art_category VARCHAR(80) DEFAULT NULL AFTER avatar_path,
    ADD COLUMN art_form VARCHAR(120) DEFAULT NULL AFTER art_category,
    ADD COLUMN years_experience VARCHAR(20) DEFAULT NULL AFTER art_form,
    ADD COLUMN awards TEXT DEFAULT NULL AFTER years_experience,

    ADD COLUMN qualification VARCHAR(60) DEFAULT NULL AFTER awards,
    ADD COLUMN institution VARCHAR(160) DEFAULT NULL AFTER qualification,
    ADD COLUMN cert_path VARCHAR(300) DEFAULT NULL AFTER institution,

    ADD COLUMN student_levels VARCHAR(120) DEFAULT NULL AFTER cert_path,
    ADD COLUMN languages VARCHAR(200) DEFAULT NULL AFTER student_levels,
    ADD COLUMN age_group_pref VARCHAR(80) DEFAULT NULL AFTER languages,

    ADD COLUMN instagram VARCHAR(120) DEFAULT NULL AFTER age_group_pref,
    ADD COLUMN linkedin VARCHAR(200) DEFAULT NULL AFTER instagram,
    ADD COLUMN youtube VARCHAR(200) DEFAULT NULL AFTER linkedin,
    ADD COLUMN portfolio_url VARCHAR(300) DEFAULT NULL AFTER youtube,
    ADD COLUMN bio TEXT DEFAULT NULL AFTER portfolio_url,
    ADD COLUMN equipment_needed TEXT DEFAULT NULL AFTER bio,

    ADD COLUMN is_approved TINYINT(1) DEFAULT 0 AFTER equipment_needed,
    ADD COLUMN reg_step TINYINT DEFAULT 0 AFTER is_approved;

CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    otp_code CHAR(4) NOT NULL,
    channel ENUM('email','phone') DEFAULT 'email',
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS portfolio_images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    file_path VARCHAR(300) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE
);

UPDATE teachers
SET is_approved = 1
WHERE teacher_id = 1;