-- Certificate email delivery tracking (safe to run multiple times)
ALTER TABLE certificates
    ADD COLUMN IF NOT EXISTS email_sent TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS email_sent_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS email_error VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS email_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending';

-- MySQL < 8.0 may not support IF NOT EXISTS on ADD COLUMN — use ensureCertificatesSchema() in PHP for compatibility.
