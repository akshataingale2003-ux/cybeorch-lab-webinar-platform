-- Admin panel password reset tokens (one-time use, hashed storage)
CREATE TABLE IF NOT EXISTS admin_password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_admin_password_reset_token_hash (token_hash),
    INDEX idx_admin_password_reset_admin (admin_id),
    INDEX idx_admin_password_reset_expires (expires_at),
    CONSTRAINT fk_admin_password_reset_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
