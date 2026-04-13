USE kathakali_bridge;

ALTER TABLE teachers
    ADD COLUMN otp_code CHAR(4) DEFAULT NULL,
    ADD COLUMN otp_expires DATETIME DEFAULT NULL;