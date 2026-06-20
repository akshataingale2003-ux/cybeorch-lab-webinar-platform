-- Create missing attendance table (webinar join/duration tracking)
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webinar_id INT NOT NULL,
    joined_at DATETIME DEFAULT NULL,
    left_at DATETIME DEFAULT NULL,
    duration_mins INT DEFAULT 0,
    marked_by ENUM('self','admin','auto') DEFAULT 'auto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attendance_user (user_id),
    INDEX idx_attendance_webinar (webinar_id)
);
