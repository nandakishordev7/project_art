USE kathakali_bridge;

CREATE TABLE IF NOT EXISTS sync_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(60) NOT NULL,
    status VARCHAR(10) NOT NULL,
    detail TEXT,
    ref_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE assignments
    ADD COLUMN feedback TEXT DEFAULT NULL;