-- Digital certificate system for webinars and bootcamps
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    event_type ENUM('webinar','bootcamp') NOT NULL,
    event_id INT NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    issue_date DATE NOT NULL,
    qr_code VARCHAR(500) DEFAULT NULL,
    pdf_path VARCHAR(500) DEFAULT NULL,
    png_path VARCHAR(500) DEFAULT NULL,
    jpg_path VARCHAR(500) DEFAULT NULL,
    email_sent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_certificate_id (certificate_id),
    UNIQUE KEY uniq_user_event (user_id, event_type, event_id),
    INDEX idx_cert_user (user_id),
    INDEX idx_cert_event (event_type, event_id),
    INDEX idx_cert_issue (issue_date)
);

CREATE TABLE IF NOT EXISTS certificate_id_sequences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year SMALLINT NOT NULL,
    month TINYINT NOT NULL,
    last_seq INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_cert_seq_year_month (year, month)
);
