-- Upgrade recorded_sessions table (add program linking columns)
ALTER TABLE recorded_sessions
    ADD COLUMN IF NOT EXISTS link_type ENUM('webinar','bootcamp') NOT NULL DEFAULT 'webinar' AFTER description,
    ADD COLUMN IF NOT EXISTS webinar_id INT NULL DEFAULT NULL AFTER link_type,
    ADD COLUMN IF NOT EXISTS bootcamp_id INT NULL DEFAULT NULL AFTER webinar_id;

-- MySQL 5.7 / MariaDB without IF NOT EXISTS: run these one at a time if the above fails
-- ALTER TABLE recorded_sessions ADD COLUMN link_type ENUM('webinar','bootcamp') NOT NULL DEFAULT 'webinar' AFTER description;
-- ALTER TABLE recorded_sessions ADD COLUMN webinar_id INT NULL DEFAULT NULL AFTER link_type;
-- ALTER TABLE recorded_sessions ADD COLUMN bootcamp_id INT NULL DEFAULT NULL AFTER webinar_id;
