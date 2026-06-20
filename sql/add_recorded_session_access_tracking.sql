-- Watch tracking and chunked upload support for recorded sessions
CREATE TABLE IF NOT EXISTS recorded_session_watch_stats (
    user_id INT NOT NULL,
    session_id INT NOT NULL,
    completed_views INT NOT NULL DEFAULT 0,
    session_peak_position DOUBLE NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, session_id),
    INDEX idx_rsw_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recorded_session_upload_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    upload_token VARCHAR(64) NOT NULL,
    admin_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    temp_path VARCHAR(500) NOT NULL,
    file_ext VARCHAR(12) NOT NULL,
    total_size BIGINT UNSIGNED NOT NULL,
    received_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mime VARCHAR(120) DEFAULT NULL,
    status ENUM('pending','complete','claimed','failed','cancelled') NOT NULL DEFAULT 'pending',
    final_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uk_rs_upload_token (upload_token),
    INDEX idx_rs_upload_admin (admin_id),
    INDEX idx_rs_upload_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
